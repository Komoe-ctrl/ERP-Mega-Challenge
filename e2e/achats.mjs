/**
 * Parcours fonctionnel du processus d'achat, dans le navigateur, avec de vrais comptes :
 * bon de commande (Service Achats) → réception → facture fournisseur (Comptabilité) →
 * contrôle à trois voies et paiement (Finance) → évaluation du fournisseur.
 *
 * Prérequis (local) : php artisan erp:e2e-user, démo semée (demandes d'achat validées, fournisseurs).
 *   docker compose exec -T laravel.test node e2e/achats.mjs
 */
import { createHmac } from 'node:crypto';
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import { chromium } from 'playwright-core';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost';
const env = Object.fromEntries(
    fs
        .readFileSync(new URL('../.env', import.meta.url), 'utf8')
        .split('\n')
        .filter((l) => l.includes('=') && !l.startsWith('#'))
        .map((l) => [
            l.slice(0, l.indexOf('=')),
            l.slice(l.indexOf('=') + 1).replace(/^"|"$/g, ''),
        ]),
);
const [local, domain] = env.E2E_EMAIL.split('@');

function totp(secret) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const c of secret.toUpperCase().replace(/=+$/, ''))
        bits += alphabet.indexOf(c).toString(2).padStart(5, '0');
    const key = Buffer.from(bits.match(/.{8}/g).map((b) => parseInt(b, 2)));
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
    const h = createHmac('sha1', key).update(counter).digest();
    const o = h[h.length - 1] & 15;
    const n =
        ((h[o] & 0x7f) << 24) | (h[o + 1] << 16) | (h[o + 2] << 8) | h[o + 3];

    return String(n % 1_000_000).padStart(6, '0');
}

// Fortify refuse de réutiliser un code TOTP dans la même fenêtre de 30 s : on attend la suivante.
let lastWindow = 0;
async function freshCode() {
    while (Math.floor(Date.now() / 30000) === lastWindow)
        await new Promise((r) => setTimeout(r, 1000));
    lastWindow = Math.floor(Date.now() / 30000);

    return totp(env.E2E_TOTP_SECRET);
}

// Une demande validée neuve à chaque exécution : le parcours est rejouable.
execSync('php artisan erp:e2e-demande', {
    cwd: new URL('..', import.meta.url).pathname,
    stdio: 'inherit',
});

const browser = await chromium.launch();
const failures = [];
const check = (ok, msg) => {
    console.log(`${ok ? '✓' : '✗'} ${msg}`);
    if (!ok) failures.push(msg);
};

async function login(role) {
    const ctx = await browser.newContext({
        viewport: { width: 1500, height: 900 },
    });
    const page = await ctx.newPage();
    await page.goto(`${BASE}/login`);
    await page.waitForLoadState('networkidle');
    await page.fill('#email', `${local}.${role}@${domain}`);
    await page.fill('#password', env.E2E_PASSWORD);
    await page.click('[data-test=login-button]');
    await page.waitForURL((u) => !u.pathname.endsWith('/login'), {
        timeout: 90000,
        waitUntil: 'commit',
    });
    if (page.url().includes('two-factor-challenge')) {
        await page.waitForLoadState('networkidle');
        await page.locator('input').first().focus();
        await page.keyboard.type(await freshCode());
        await page.locator('button[type=submit]').click();
        await page.waitForURL(
            (u) => !u.pathname.includes('two-factor-challenge'),
            { timeout: 90000, waitUntil: 'commit' },
        );
    }
    await page.waitForSelector('[data-test="legacy-header"]', {
        timeout: 120000,
    });
    await page.waitForLoadState('networkidle');

    return { ctx, page };
}

const settle = (page) =>
    page.waitForLoadState('networkidle').then(() => page.waitForTimeout(500));
const bodyText = (page) => page.locator('body').innerText();

// --- 1. Bon de commande (Service Achats) ---------------------------------------------
let { ctx, page } = await login('service_achats_btp');
await page.goto(`${BASE}/achats/commandes`);
await settle(page);
check(
    (await page.locator('h1').first().innerText()).includes('Bons de commande'),
    'la liste des bons de commande s’ouvre',
);
await page.getByRole('button', { name: 'Nouveau bon de commande' }).click();
const dialog = page.getByRole('dialog');
await dialog.waitFor();
const demandSelect = dialog.getByLabel('Demande validée');
const demandOptions = await demandSelect.locator('option').allInnerTexts();
check(demandOptions.length > 1, 'des demandes validées sont proposées');
await demandSelect.selectOption({ index: demandOptions.length - 1 });
await dialog.getByLabel('Fournisseur').selectOption({ index: 1 });
await dialog.getByRole('button', { name: 'Émettre' }).click();
await page
    .waitForURL(/\/achats\/commandes\/\d+$/, { timeout: 60000 })
    .catch(() => {});
await settle(page);
const orderUrl = page.url();
check(
    /\/achats\/commandes\/\d+$/.test(orderUrl),
    'le bon de commande est émis et sa fiche s’ouvre',
);
const orderNumber = (await page.locator('h1').first().innerText()).trim();
check(/^BC-/.test(orderNumber), `le bon est numéroté (${orderNumber})`);

// --- 2. Réception ----------------------------------------------------------------------
await page.getByRole('link', { name: 'Réceptionner' }).click();
await page.waitForURL(/\/reception$/, { timeout: 60000 });
await settle(page);
check(
    (await page.locator('h1').first().innerText()).includes('Réception de'),
    'la page de réception s’ouvre avec les lignes du bon',
);
await page.getByRole('button', { name: 'Enregistrer la réception' }).click();
await page.waitForURL(orderUrl, { timeout: 60000 }).catch(() => {});
await settle(page);
check(
    (await bodyText(page)).includes('BR-'),
    'la réception conforme est enregistrée (bon de réception numéroté)',
);
const orderAmount = Number(
    (await page.locator('tfoot').innerText()).replace(/[^\d]/g, '').slice(0),
);
await ctx.close();

// --- 3. Facture fournisseur (Comptabilité) --------------------------------------------
({ ctx, page } = await login('comptabilite'));
await page.goto(`${BASE}/achats/factures`);
await settle(page);
await page.getByRole('button', { name: 'Saisir une facture' }).click();
const inv = page.getByRole('dialog');
await inv.waitFor();
const invoiceNumber = `FA-E2E-${Date.now().toString().slice(-6)}`;
await inv.getByLabel('N° de facture fournisseur').fill(invoiceNumber);
const orderOptions = await inv
    .getByLabel('Bon de commande')
    .locator('option')
    .allInnerTexts();
const mine = orderOptions.find((o) => o.startsWith(orderNumber));
check(!!mine, 'le bon réceptionné est proposé pour la facture');
await inv.getByLabel('Bon de commande').selectOption({ label: mine });
const receptionOptions = await inv
    .getByLabel('Réception rapprochée')
    .locator('option')
    .allInnerTexts();
const reception = receptionOptions.find((o) => o.includes(orderNumber));
if (reception)
    await inv
        .getByLabel('Réception rapprochée')
        .selectOption({ label: reception });
await inv.getByLabel('Montant facturé').fill(String(orderAmount));
const today = new Date().toISOString().slice(0, 10);
await inv.getByLabel('Date de facture').fill(today);
await inv
    .getByLabel('Échéance')
    .fill(new Date(Date.now() + 30 * 864e5).toISOString().slice(0, 10));
await inv.getByRole('button', { name: /Enregistrer|Valider|Créer/ }).click();
await page
    .waitForFunction(
        (n) =>
            [...document.querySelectorAll('tr')].some((r) =>
                r.innerText.includes(n),
            ),
        invoiceNumber,
        { timeout: 20000 },
    )
    .catch(() => {});
await settle(page);
check(
    (await page.locator('tr', { hasText: invoiceNumber }).count()) === 1,
    'la facture est enregistrée et apparaît dans la liste',
);
await ctx.close();

// --- 4. Contrôle à trois voies et paiement (Finance) ----------------------------------
({ ctx, page } = await login('finance'));
await page.goto(`${BASE}/achats/factures`);
await settle(page);
let row = page.locator('tr', { hasText: invoiceNumber });
await row.getByRole('button', { name: 'Contrôler' }).click();
await page
    .waitForFunction(
        (n) =>
            [...document.querySelectorAll('tr')].some(
                (r) =>
                    r.innerText.includes(n) &&
                    r.innerText.includes('Contrôlée'),
            ),
        invoiceNumber,
        { timeout: 20000 },
    )
    .catch(() => {});
await settle(page);
row = page.locator('tr', { hasText: invoiceNumber });
check(
    (await row.innerText()).includes('Contrôlée'),
    'le contrôle à trois voies concorde : facture « Contrôlée »',
);
await row.getByRole('button', { name: 'Payer' }).click();
await page
    .waitForFunction(
        (n) =>
            [...document.querySelectorAll('tr')].some(
                (r) => r.innerText.includes(n) && r.innerText.includes('Payée'),
            ),
        invoiceNumber,
        { timeout: 20000 },
    )
    .catch(() => {});
check(
    (await page.locator('tr', { hasText: invoiceNumber }).innerText()).includes(
        'Payée',
    ),
    'la facture est payée',
);
await ctx.close();

// --- 5. Évaluation du fournisseur (Service Achats) ------------------------------------
({ ctx, page } = await login('service_achats_btp'));
await page.goto(orderUrl);
await settle(page);
await page
    .locator('main, body')
    .first()
    .getByRole('link', { name: /Fournisseur|^\S/ })
    .first()
    .isVisible()
    .catch(() => false);
const supplierHref = await page
    .locator('a[href^="/fournisseurs/"]')
    .first()
    .getAttribute('href')
    .catch(() => null);
if (supplierHref) {
    await page.goto(`${BASE}${supplierHref}`);
    await settle(page);
    await page.getByRole('button', { name: 'Évaluer une réception' }).click();
    const ev = page.getByRole('dialog');
    await ev.waitFor();
    await ev.getByLabel('Réception évaluée').selectOption({ index: 1 });
    await ev.getByLabel(/Respect des délais/).fill('5');
    await ev.getByLabel(/Qualité/).fill('4');
    await ev.getByLabel(/Respect des prix/).fill('4');
    await ev.getByRole('button', { name: /Enregistrer|Valider|Créer/ }).click();
    await page
        .waitForFunction(() => document.body.innerText.includes('/ 5'), null, {
            timeout: 20000,
        })
        .catch(() => {});
    await settle(page);
    check(
        (await bodyText(page)).includes('/ 5'),
        'la note moyenne du fournisseur est calculée après l’évaluation',
    );
} else {
    check(false, 'lien vers le fournisseur introuvable sur la fiche du bon');
}
await ctx.close();

await browser.close();
console.log(`\n${failures.length} échec(s).`);
process.exit(failures.length ? 1 : 0);
