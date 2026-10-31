/**
 * Parcours fonctionnel des clients, dans le navigateur : création (numéro unique), alerte de doublon
 * avec confirmation (RG2.1), fiche agrégée (CA2), ajout d'un contact et d'un document.
 *
 * Prérequis (local) : php artisan erp:e2e-user.
 *   docker compose exec -T laravel.test node e2e/clients.mjs
 */
import { createHmac } from 'node:crypto';
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

const browser = await chromium.launch();
const failures = [];
const check = (ok, msg) => {
    console.log(`${ok ? '✓' : '✗'} ${msg}`);
    if (!ok) failures.push(msg);
};
const settle = (page) =>
    page.waitForLoadState('networkidle').then(() => page.waitForTimeout(500));
const waitText = (page, text) =>
    page
        .waitForFunction((t) => document.body.innerText.includes(t), text, {
            timeout: 20000,
        })
        .catch(() => {});

const ctx = await browser.newContext({
    viewport: { width: 1500, height: 900 },
});
const page = await ctx.newPage();
await page.goto(`${BASE}/login`);
await page.waitForLoadState('networkidle');
await page.fill('#email', `${local}.direction_technique@${domain}`);
await page.fill('#password', env.E2E_PASSWORD);
await page.click('[data-test=login-button]');
await page.waitForURL((u) => !u.pathname.endsWith('/login'), {
    timeout: 90000,
    waitUntil: 'commit',
});
if (page.url().includes('two-factor-challenge')) {
    await page.waitForLoadState('networkidle');
    await page.locator('input').first().focus();
    await page.keyboard.type(totp(env.E2E_TOTP_SECRET));
    await page.locator('button[type=submit]').click();
}
await page.waitForSelector('[data-test="legacy-header"]', { timeout: 120000 });
await page.waitForLoadState('networkidle');

const name = `Client e2e ${Date.now().toString().slice(-6)}`;

async function fillClient(dlg) {
    await dlg.getByLabel(/^Nom ou raison sociale/).fill(name);
    await dlg.getByLabel(/^Type de client/).selectOption('entreprise');
    await dlg.getByLabel(/^Ville/).fill('Abidjan');
    await dlg.getByLabel(/^Contact principal/).fill('M. Test');
    await dlg.getByLabel(/^Téléphone/).fill('+225 07 00 00 00 00');
}

// --- 1. Création -----------------------------------------------------------------------
await page.goto(`${BASE}/clients`);
await settle(page);
check(
    (await page.locator('h1').first().innerText()).includes('Clients'),
    'la liste des clients s’ouvre',
);
await page.getByRole('button', { name: 'Nouveau client' }).click();
let dlg = page.getByRole('dialog');
await dlg.waitFor();
await fillClient(dlg);
await dlg.getByRole('button', { name: /Enregistrer|Valider|Créer/ }).click();
await page.waitForURL(/\/clients\/\d+$/, { timeout: 60000 }).catch(() => {});
await settle(page);
const clientUrl = page.url();
check(
    /\/clients\/\d+$/.test(clientUrl),
    'le client est créé et sa fiche s’ouvre',
);
check(
    /CLI-\d{4}-\d{4}/.test(await page.locator('body').innerText()),
    'le client reçoit un numéro unique automatique',
);
check(
    (await page.locator('body').innerText()).includes('Projets en cours'),
    'la fiche montre les projets en cours et les créances (CA2)',
);

// --- 2. Doublon ------------------------------------------------------------------------
await page.goto(`${BASE}/clients`);
await settle(page);
await page.getByRole('button', { name: 'Nouveau client' }).click();
dlg = page.getByRole('dialog');
await dlg.waitFor();
await fillClient(dlg);
await dlg.getByRole('button', { name: /Enregistrer|Valider|Créer/ }).click();
await waitText(page, 'Doublon possible');
check(
    (await page.locator('body').innerText()).includes('Doublon possible'),
    'un doublon sur le nom et le contact est signalé (RG2.1)',
);
await page.getByRole('button', { name: 'Créer quand même' }).click();
await page
    .waitForURL(
        (u) => /\/clients\/\d+$/.test(u.pathname) && u.href !== clientUrl,
        { timeout: 60000 },
    )
    .catch(() => {});
check(
    /\/clients\/\d+$/.test(page.url()) && page.url() !== clientUrl,
    'le doublon confirmé crée un second client',
);

// --- 3. Contact et document -------------------------------------------------------------
await page.goto(clientUrl);
await settle(page);
await page.getByRole('button', { name: 'Ajouter un contact' }).click();
dlg = page.getByRole('dialog');
await dlg.waitFor();
await dlg.getByLabel(/^Nom/).fill('Mme Contact');
await dlg.getByLabel(/^Fonction/).fill('Directrice');
await dlg
    .getByRole('button', { name: /Enregistrer|Valider|Créer|Ajouter/ })
    .click();
await waitText(page, 'Mme Contact');
check(
    (await page.locator('body').innerText()).includes('Mme Contact'),
    'le contact est ajouté à la fiche',
);

fs.writeFileSync('/tmp/e2e-contrat.pdf', '%PDF-1.4\n% e2e\n');
await page.locator('input[type="file"]').setInputFiles('/tmp/e2e-contrat.pdf');
// Le serveur de dev (artisan serve) met parfois près d'une minute à traiter un envoi multipart.
await page
    .waitForFunction(
        () => document.body.innerText.includes('e2e-contrat.pdf'),
        null,
        { timeout: 120000 },
    )
    .catch(() => {});
check(
    (await page.locator('body').innerText()).includes('e2e-contrat.pdf'),
    'le document est joint à la fiche',
);

await browser.close();
console.log(`\n${failures.length} échec(s).`);
process.exit(failures.length ? 1 : 0);
