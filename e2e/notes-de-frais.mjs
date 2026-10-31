/**
 * Parcours fonctionnel d'une note de frais, dans le navigateur :
 * saisie (conducteur de travaux) avec panneau budget → soumission → validation du chef de service
 * (Direction technique) → approbation du Directeur (Direction générale) → remboursement (Comptabilité)
 * → suivi workflow. Vérifie aussi que les montants sont calculés et que le panneau budget s'affiche.
 *
 * Prérequis (local) : php artisan erp:e2e-user, PiecesSeeder joué (circuit, rubriques, exercice ouvert).
 *   docker compose exec -T laravel.test node e2e/notes-de-frais.mjs
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

// Fortify refuse de réutiliser un code TOTP dans la même fenêtre de 30 s : on attend la suivante.
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
    page.waitForLoadState('networkidle').then(() => page.waitForTimeout(400));

// --- 1. Saisie par le conducteur de travaux ------------------------------------------
let { ctx, page } = await login('conducteur_travaux');
await page.goto(`${BASE}/pieces/note_frais`);
await settle(page);
check(
    (await page.locator('h1').first().innerText()).includes('Note de frais'),
    'la liste des notes de frais s’ouvre',
);

await page.getByRole('button', { name: 'Nouveau' }).click();
await page.locator('[data-test="piece-form"]').waitFor();
const title = `Mission e2e ${Date.now()}`;
await page.locator('[data-test="piece-form"] input').nth(2).fill(title);
await page.getByLabel('Désignation ligne 1').fill('Transport aller-retour');
await page.getByLabel('Quantité ligne 1').fill('2');
await page.getByLabel('Prix unitaire ligne 1').fill('18000');
const options = await page
    .getByLabel('Rubrique ligne 1')
    .locator('option')
    .allInnerTexts();
const rubrique = options.find((o) => o.startsWith('625'));
check(!!rubrique, 'la rubrique 625 est proposée');
await page.getByLabel('Rubrique ligne 1').selectOption({ label: rubrique });
await page
    .waitForFunction(
        () =>
            document
                .querySelector('[data-test="budget-panel"]')
                ?.innerText.includes('Montant budget'),
        null,
        { timeout: 20000 },
    )
    .catch(() => {});

check(
    (await page.locator('[data-test="line-amount"]').first().innerText())
        .replace(/\s/g, '')
        .includes('36000'),
    'le montant de la ligne est calculé (2 × 18 000)',
);
const panel = await page.locator('[data-test="budget-panel"]').innerText();
check(
    panel.includes('Montant budget') &&
        panel.includes('Budget restant') &&
        panel.includes('Taux de consommation'),
    'le panneau budget affiche montant, consommé + en cours, restant, taux',
);

await page.getByRole('button', { name: 'Valider la saisie' }).click();
await page.waitForURL(/\/pieces\/note_frais\/\d+$/, { timeout: 60000 });
await settle(page);
const pieceUrl = page.url();
check(
    /\/pieces\/note_frais\/\d+$/.test(pieceUrl),
    'la note de frais est enregistrée et son détail s’ouvre',
);
check(
    (await page.locator('[data-test="piece-total"]').innerText())
        .replace(/\s/g, '')
        .includes('36000'),
    'le total enregistré est celui du serveur',
);

await page.locator('[data-test="submit-piece"]').click();
await page
    .waitForFunction(
        () =>
            document
                .querySelector('[data-test="piece-show"]')
                ?.innerText.includes('En validation'),
        null,
        { timeout: 30000 },
    )
    .catch(() => {});
await settle(page);
check(
    (await page.locator('[data-test="piece-show"]').innerText()).includes(
        'En validation',
    ),
    'la note est soumise : état « En validation »',
);
await ctx.close();

// --- 2. Validation du chef de service puis du Directeur ------------------------------
for (const [role, step] of [
    ['direction_technique', 'chef de service'],
    ['direction_generale', 'Directeur'],
]) {
    ({ ctx, page } = await login(role));
    await page.goto(`${BASE}/pieces/note_frais/validation`);
    await settle(page);
    const row = page.locator('tr', { hasText: title });
    check(
        (await row.count()) === 1,
        `${step} : la note attend son étape dans « Validation »`,
    );
    await page.goto(pieceUrl);
    await settle(page);
    check(
        (await page.locator('[data-test="advance-piece"]').count()) === 1,
        `${step} : le bouton de son niveau est visible`,
    );
    await page.locator('[data-test="advance-piece"]').click();
    await settle(page);
    await ctx.close();
}

// --- 3. Approuvée, remboursement par la Comptabilité ----------------------------------
({ ctx, page } = await login('comptabilite'));
await page.goto(pieceUrl);
await settle(page);
check(
    (await page.locator('[data-test="piece-show"]').innerText()).includes(
        'Approuvée',
    ),
    'la note est approuvée après les deux étapes',
);
await page.goto(`${BASE}/pieces/note_frais/remboursements`);
await settle(page);
await page
    .locator('tr', { hasText: title })
    .getByRole('button', { name: 'Rembourser' })
    .click();
await page
    .waitForFunction(
        (t) =>
            [...document.querySelectorAll('tr')].some(
                (r) => r.innerText.includes(t) && r.innerText.includes('FN-'),
            ),
        title,
        { timeout: 20000 },
    )
    .catch(() => {});
await settle(page);
check(
    (await page.locator('tr', { hasText: title }).innerText()).includes('FN-'),
    'la facture de remboursement est numérotée',
);

// --- 4. Suivi workflow ----------------------------------------------------------------
await page.goto(`${BASE}/pieces/note_frais/suivi`);
await settle(page);
const suiviSelect = page.locator('[data-test="pieces-suivi"] select');
await suiviSelect.selectOption({
    label: (
        await suiviSelect
            .locator('option', { hasText: title })
            .first()
            .innerText()
    ).trim(),
});
await settle(page);
const suivi = await page.locator('[data-test="pieces-suivi"]').innerText();
check(
    ['Soumise', 'Étape validée', 'Approuvée', 'Remboursée'].every((w) =>
        suivi.includes(w),
    ),
    'le suivi workflow montre soumission, validation, approbation et remboursement',
);
await ctx.close();

await browser.close();
console.log(`\n${failures.length} échec(s).`);
process.exit(failures.length ? 1 : 0);
