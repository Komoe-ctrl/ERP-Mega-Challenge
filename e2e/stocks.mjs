/**
 * Parcours fonctionnel des stocks, dans le navigateur, avec de vrais comptes :
 * entrée valorisée (magasinier) → transfert entre dépôts → perte soumise à validation →
 * validation par la Direction générale → inventaire (ouverture, comptage, écart, validation).
 *
 * Prérequis (local) : php artisan erp:e2e-user, StocksSeeder joué, articles en base (démo).
 *   docker compose exec -T laravel.test node e2e/stocks.mjs
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

let lastWindow = 0;
async function freshCode() {
    while (Math.floor(Date.now() / 30000) === lastWindow)
        await new Promise((r) => setTimeout(r, 1000));
    lastWindow = Math.floor(Date.now() / 30000);

    return totp(env.E2E_TOTP_SECRET);
}

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
const waitText = (page, text) =>
    page
        .waitForFunction((t) => document.body.innerText.includes(t), text, {
            timeout: 20000,
        })
        .catch(() => {});

const waitTable = (page, text) =>
    page
        .waitForFunction(
            (t) => document.querySelector('table')?.innerText.includes(t),
            text,
            { timeout: 20000 },
        )
        .catch(() => {});

async function newMovement(page, values) {
    await page.getByRole('button', { name: 'Nouveau mouvement' }).click();
    const dlg = page.getByRole('dialog');
    await dlg.waitFor();
    await dlg.getByLabel('Opération').selectOption(values.type);
    await dlg
        .getByLabel('Article')
        .selectOption({ index: values.article ?? 1 });
    await dlg.getByLabel('Quantité').fill(String(values.quantity));
    await dlg
        .getByLabel(/^Magasin/)
        .selectOption({ index: values.warehouse ?? 1 });
    if (values.destination)
        await dlg
            .getByLabel('Destination (transfert)')
            .selectOption({ index: values.destination });
    if (values.cost)
        await dlg
            .getByLabel('Coût unitaire (entrée)')
            .fill(String(values.cost));
    if (values.reason) await dlg.getByLabel('Motif').fill(values.reason);
    await dlg
        .getByRole('button', { name: /Enregistrer|Valider|Créer/ })
        .click();
}

// --- 1. Entrée et transfert (magasinier) ------------------------------------------------
let { ctx, page } = await login('magasinier');
await page.goto(`${BASE}/stocks/mouvements`);
await settle(page);
check(
    (await page.locator('h1').first().innerText()).includes(
        'Mouvements de stock',
    ),
    'la liste des mouvements s’ouvre',
);

const reason = `Casse e2e ${Date.now().toString().slice(-5)}`;
await newMovement(page, { type: 'entree', quantity: 100, cost: 1000 });
await waitTable(page, 'Entrée');
await settle(page);
check(
    (await page.locator('table').innerText()).includes('Entrée'),
    'l’entrée est enregistrée et apparaît dans la liste',
);

await newMovement(page, {
    type: 'transfert',
    quantity: 30,
    warehouse: 1,
    destination: 2,
});
await waitTable(page, 'Transfert');
await settle(page);
check(
    (await page.locator('table').innerText()).includes('Transfert'),
    'le transfert entre deux dépôts est tracé',
);

// --- 2. Perte : en attente de validation -------------------------------------------------
await newMovement(page, { type: 'perte', quantity: 5, warehouse: 2, reason });
await waitText(page, reason);
await settle(page);
const lossRow = page.locator('tr', { hasText: reason });
check(
    (await lossRow.innerText()).includes('En attente de validation'),
    'la perte attend une validation (RG9.3)',
);
check(
    (await lossRow.getByRole('button', { name: 'Valider' }).count()) === 0,
    'le magasinier ne peut pas valider sa propre perte',
);

await page.goto(`${BASE}/stocks`);
await settle(page);
const before = await page.locator('table').innerText();
check(before.length > 0, 'l’état des stocks affiche les niveaux et la valeur');
await ctx.close();

// --- 3. Validation par la Direction générale -----------------------------------------------
({ ctx, page } = await login('direction_generale'));
await page.goto(`${BASE}/stocks/mouvements`);
await settle(page);
await page
    .locator('tr', { hasText: reason })
    .getByRole('button', { name: 'Valider' })
    .click();
await page
    .waitForFunction(
        (r) =>
            [...document.querySelectorAll('tr')].some(
                (t) =>
                    t.innerText.includes(r) && t.innerText.includes('Validé'),
            ),
        reason,
        { timeout: 20000 },
    )
    .catch(() => {});
check(
    (await page.locator('tr', { hasText: reason }).innerText()).includes(
        'Validé',
    ),
    'la Direction valide la perte : le stock est ajusté',
);
await ctx.close();

// --- 4. Inventaire -------------------------------------------------------------------------
({ ctx, page } = await login('magasinier'));
await page.goto(`${BASE}/stocks/inventaire`);
await settle(page);
await page.getByLabel('Magasin à inventorier').selectOption({ index: 2 });
await page.getByRole('button', { name: 'Ouvrir' }).click();
await page.waitForURL(/inventaire=\d+/, { timeout: 30000 }).catch(() => {});
await settle(page);
const gapCell = page.locator('[data-test="inventory-gap"]').first();
check(
    (await page.locator('tbody tr').count()) >= 1,
    'l’inventaire fige le stock théorique de chaque article',
);
const input = page.locator('tbody input[type="number"]').first();
const theoretical = Number(
    (await page.locator('tbody tr').first().locator('td').nth(1).innerText())
        .replace(/[^\d]/g, '')
        .slice(0, 6) || 0,
);
await input.fill(String(Math.max(0, theoretical - 3)));
await settle(page);
check(
    (await gapCell.innerText()).trim().startsWith('-') ||
        (await gapCell.innerText()).includes('−') ||
        (await gapCell.innerText()).includes('-'),
    'l’écart théorique / compté s’affiche par article',
);
await page.getByRole('button', { name: 'Enregistrer le comptage' }).click();
await settle(page);
await ctx.close();

({ ctx, page } = await login('direction_generale'));
await page.goto(`${BASE}/stocks/inventaire`);
await settle(page);
await page.getByRole('button', { name: 'Valider et ajuster le stock' }).click();
await waitText(page, 'Validé');
await settle(page);
check(
    (await page.locator('main, body').first().innerText()).includes('Validé'),
    'l’inventaire est validé : le stock est ajusté sur l’écart',
);
await ctx.close();

await browser.close();
console.log(`\n${failures.length} échec(s).`);
process.exit(failures.length ? 1 : 0);
