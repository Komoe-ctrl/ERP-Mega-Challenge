/**
 * Parcours des devis, dans le navigateur : brouillon, ligne libre, enregistrement, soumission.
 *
 *
 * Prérequis (local) : php artisan erp:e2e-user.
 *   docker compose exec -T laravel.test node e2e/devis.mjs
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

// --- 1. Création d'un brouillon ---------------------------------------------------------
await page.goto(`${BASE}/devis`);
await settle(page);
check(
    (await page.locator('h1').first().innerText()).includes('Devis'),
    'la liste des devis s’ouvre',
);
await page.getByRole('button', { name: 'Nouveau devis' }).click();
let dlg = page.getByRole('dialog');
await dlg.waitFor();
await dlg.getByLabel(/^Projet/).selectOption({ index: 1 });
await dlg.getByRole('button', { name: /Créer le brouillon/ }).click();
await page.waitForURL(/\/devis\/\d+$/, { timeout: 60000 }).catch(() => {});
await settle(page);
check(/\/devis\/\d+$/.test(page.url()), 'le brouillon est créé et s’ouvre');
check(
    /DEV-\d{4}-\d{5}/.test(await page.locator('body').innerText()),
    'le devis reçoit un numéro unique automatique',
);

// --- 2. Édition : ligne libre, totaux, enregistrement ------------------------------------
await page.getByRole('button', { name: 'Ligne libre' }).click();
await page.getByLabel('Libellé ouvrage 1').fill('Maçonnerie e2e');
await page.getByLabel('Quantité ouvrage 1').fill('10');
await page.getByLabel('Prix unitaire ouvrage 1').fill('15000');
check(
    (await page.locator('body').innerText()).includes('150'),
    'le total de la ligne se calcule (10 × 15 000)',
);
await page.getByRole('button', { name: 'Enregistrer' }).click();
await waitText(page, 'Devis enregistré');
await page.reload();
await settle(page);
check(
    (await page.getByLabel('Libellé ouvrage 1').inputValue()) ===
        'Maçonnerie e2e',
    'la ligne est enregistrée sur le serveur',
);

// --- 3. Soumission ---------------------------------------------------------------------
await page.getByRole('button', { name: 'Soumettre' }).click();
await waitText(page, 'Devis soumis');
check(
    (await page.locator('body').innerText()).includes('Soumis'),
    'le devis est soumis pour validation',
);

await browser.close();
console.log(`\n${failures.length} échec(s).`);
process.exit(failures.length ? 1 : 0);
