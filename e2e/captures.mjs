/**
 * Captures d'écran de contrôle (tableau de bord, barre latérale active, panneau Fichier,
 * panneau de la cloche) dans storage/app/captures/ (non versionné).
 * Usage : docker compose exec -T laravel.test node e2e/captures.mjs [profil]
 */
import fs from 'node:fs';
import { chromium } from 'playwright-core';

const OUT = '/var/www/html/storage/app/captures';
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
const role = process.argv[2] ?? 'direction_technique';
fs.mkdirSync(OUT, { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1500, height: 850 } });
await page.goto('http://localhost/login');
await page.waitForLoadState('networkidle');
await page.fill('#email', `${local}.${role}@${domain}`);
await page.fill('#password', env.E2E_PASSWORD);
await page.click('[data-test=login-button]');
// La connexion est lente la première fois : on attend le bandeau de l'application.
await page.waitForSelector('[data-test="legacy-header"]', { timeout: 120000 });
await page.waitForLoadState('networkidle');

async function shot(path, name) {
    await page.goto(`http://localhost${path}`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);
    await page.screenshot({ path: `${OUT}/${name}.png` });
}

await shot('/dashboard', 'dashboard');
await shot('/achats/demandes', 'menu-actif');

await page
    .locator('nav[aria-label="Menu principal"] button:has-text("Fichier")')
    .click();
await page.waitForTimeout(400);
await page.screenshot({ path: `${OUT}/fichier.png` });
await page.keyboard.press('Escape');
await page.locator('[data-test="notifications-bell"]').click();
await page.waitForTimeout(400);
await page.screenshot({ path: `${OUT}/cloche.png` });

await browser.close();
console.log('captures écrites dans storage/app/captures/');
