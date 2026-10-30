/**
 * Parcours de navigation : se connecte, puis clique sur chaque entrée du menu
 * latéral, du menu du haut, du menu Fichier et de la cloche de notifications.
 * Pour chaque page : elle s'ouvre, le titre (h1) correspond à l'entrée, aucune
 * erreur console, aucune réponse 5xx, et la page reste cliquable
 * (`pointer-events` du body) après l'ouverture/fermeture d'un menu déroulant.
 *
 * Prérequis (local) : php artisan erp:e2e-user, puis depuis la racine du dépôt :
 *   docker compose exec -T laravel.test node e2e/navigation.mjs
 * (playwright-core + chromium : npm i -D @playwright/test && npx playwright install --with-deps chromium)
 */
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

const aliases = { 'Tableau de bord': 'bonjour' };
const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
const failures = [];
let checked = 0;

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1600, height: 1000 } });

let consoleErrors = [];
page.on(
    'console',
    (m) => m.type() === 'error' && consoleErrors.push(m.text().slice(0, 200)),
);
page.on('pageerror', (e) => consoleErrors.push(`PAGEERROR ${e.message}`));
page.on(
    'response',
    (r) =>
        r.status() >= 500 &&
        consoleErrors.push(`HTTP ${r.status()} ${r.url()}`),
);

async function settle() {
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(300);
}

/** Vérifie la page courante pour l'entrée `label` ouverte depuis `where`. */
async function verify(where, label, expectedPath) {
    checked++;
    await settle();
    const problems = [];
    const path = new URL(page.url()).pathname;
    if (expectedPath && path !== expectedPath)
        problems.push(`URL ${path} ≠ ${expectedPath}`);
    const h1 = (
        await page
            .locator('h1:visible')
            .first()
            .innerText({ timeout: 3000 })
            .catch(() => '')
    ).trim();
    if (!h1) problems.push('aucun titre h1');
    else {
        const word = aliases[label] ?? norm(label)
            .split(/[^a-z0-9]+/)
            .filter((w) => w.length >= 4)[0];
        if (word && !norm(h1).includes(word.slice(0, 5)))
            problems.push(`titre « ${h1} » ne correspond pas à « ${label} »`);
    }
    const pe = await page.evaluate(
        () => getComputedStyle(document.body).pointerEvents,
    );
    if (pe === 'none') problems.push('body pointer-events: none');
    if (consoleErrors.length)
        problems.push(`console : ${consoleErrors.join(' | ')}`);
    consoleErrors = [];
    if (problems.length)
        failures.push(`[${where}] ${label} → ${problems.join(' ; ')}`);
    console.log(
        `${problems.length ? '✗' : '✓'} [${where}] ${label}${h1 ? ` — « ${h1} »` : ''}`,
    );
}

// --- Connexion -------------------------------------------------
await page.goto(`${BASE}/login`);
await page.fill('#email', env.E2E_EMAIL);
await page.fill('#password', env.E2E_PASSWORD);
await page.click('[data-test=login-button]');
await page.waitForURL((u) => !u.pathname.endsWith('/login'), {
    timeout: 90000,
    waitUntil: 'commit',
});
await page.goto(`${BASE}/dashboard`);
await settle();
consoleErrors = [];
if (!new URL(page.url()).pathname.startsWith('/dashboard')) {
    console.error(`Connexion impossible : arrivé sur ${page.url()}`);
    process.exit(2);
}

// --- Menu latéral : chaque groupe, puis chaque entrée -------------------------
const groupButtons = page.locator(
    '[data-sidebar="content"] [data-sidebar="menu-button"]',
);
const groupCount = await groupButtons.count();
const groupLabels = [];
for (let i = 0; i < groupCount; i++) {
    const t = (await groupButtons.nth(i).innerText()).trim();
    if (t) groupLabels.push(t);
}
for (const group of groupLabels) {
    await page.goto(`${BASE}/dashboard`);
    await settle();
    await page
        .locator('[data-sidebar="content"] [data-sidebar="menu-button"]', {
            hasText: group,
        })
        .first()
        .click();
    await page.waitForTimeout(250);
    const items = await page
        .locator('[data-sidebar="menu-sub-button"]:visible')
        .evaluateAll((as) =>
            as.map((a) => ({
                label: a.textContent.trim(),
                href: a.getAttribute('href'),
            })),
        );
    if (items.length === 0)
        failures.push(
            `[menu] groupe « ${group} » sans entrée visible après clic`,
        );
    for (const item of items) {
        await page.goto(`${BASE}/dashboard`);
        await settle();
        await page
            .locator('[data-sidebar="content"] [data-sidebar="menu-button"]', {
                hasText: group,
            })
            .first()
            .click();
        await page.waitForTimeout(250);
        await page
            .locator(`[data-sidebar="menu-sub-button"][href="${item.href}"]`)
            .click();
        await page
            .waitForURL((u) => u.pathname === item.href, {
                timeout: 30000,
                waitUntil: 'commit',
            })
            .catch(() => {});
        await verify(`menu › ${group}`, item.label, item.href);
    }
}

// --- Menu du haut --------------------------------------------------------------
const top = await page
    .locator('nav[aria-label="Menu principal"] a[href]')
    .evaluateAll((as) =>
        as
            .filter((a) => a.getAttribute('href') !== '/notifications')
            .map((a) => ({
                label: a.textContent.trim(),
                href: a.getAttribute('href'),
            })),
    );
for (const item of top) {
    await page.goto(`${BASE}/dashboard`);
    await settle();
    await page
        .locator(`nav[aria-label="Menu principal"] a[href="${item.href}"]`)
        .click();
    await page
        .waitForURL((u) => u.pathname === item.href, {
            timeout: 30000,
            waitUntil: 'commit',
        })
        .catch(() => {});
    await verify('menu du haut', item.label, item.href);
}

// --- Menu Fichier (ouverture, clic, fermeture à l'échap) ------------------------
await page.goto(`${BASE}/dashboard`);
await settle();
const trigger = page.locator(
    'nav[aria-label="Menu principal"] button:has-text("Fichier")',
);
await trigger.click();
const fichier = await page
    .locator('[role="menu"] a[href]')
    .evaluateAll((as) =>
        as.map((a) => ({
            label: a.textContent.trim(),
            href: a.getAttribute('href'),
        })),
    );
await page.keyboard.press('Escape');
await page.waitForTimeout(300);
if (
    (await page.evaluate(
        () => getComputedStyle(document.body).pointerEvents,
    )) === 'none'
) {
    failures.push(
        '[Fichier] body pointer-events: none après fermeture du menu',
    );
}
for (const item of fichier) {
    await page.goto(`${BASE}/dashboard`);
    await settle();
    await trigger.click();
    await page.locator(`[role="menu"] a[href="${item.href}"]`).click();
    await page
        .waitForURL((u) => u.pathname === item.href, {
            timeout: 30000,
            waitUntil: 'commit',
        })
        .catch(() => {});
    await verify('Fichier', item.label, item.href);
}

// --- Cloche de notifications ---------------------------------------------------
await page.goto(`${BASE}/dashboard`);
await settle();
await page.locator('[data-test="notifications-bell"]').click();
await page
    .waitForURL((u) => u.pathname === '/notifications', {
        timeout: 30000,
        waitUntil: 'commit',
    })
    .catch(() => {});
await verify('en-tête', 'Notifications', '/notifications');

// --- Menu utilisateur : ouvrir/fermer puis rester cliquable ---------------------
await page.goto(`${BASE}/dashboard`);
await settle();
await page.locator('[data-sidebar="footer"] button').last().click();
await page.keyboard.press('Escape');
await page.waitForTimeout(300);
await groupButtons.first().click();
await verify('après menu utilisateur', 'Bonjour', null);

await browser.close();
console.log(`\n${checked} pages vérifiées, ${failures.length} échec(s).`);
failures.forEach((f) => console.log(`  ✗ ${f}`));
process.exit(failures.length ? 1 : 0);
