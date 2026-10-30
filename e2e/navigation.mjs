/**
 * Parcours de navigation, pour un compte de chaque profil (et un compte qui voit
 * tout). Pour chacun :
 *  - la bonne page d'arrivée après la connexion (jamais les paramètres) ;
 *  - le titre et le service du poste dans l'en-tête, sans logo, en-tête fixe ;
 *  - l'accordéon : clic = ouverture, chevron qui pivote, une seule catégorie
 *    ouverte, catégorie de la page courante ouverte, écran courant en évidence ;
 *  - chaque entrée du menu latéral, du menu du haut, du menu Fichier et la
 *    cloche : la page s'ouvre (pas de 403), le titre h1 correspond, aucune erreur
 *    console, la page reste cliquable (`pointer-events` du body).
 *
 * Prérequis (local) : php artisan erp:e2e-user (comptes e2e.<profil>@…), puis
 *   docker compose exec -T laravel.test node e2e/navigation.mjs [profil…]
 * (playwright-core + chromium : npm i -D @playwright/test && npx playwright install --with-deps chromium)
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

/** Oracle indépendant du code : arrivée, poste et service attendus par profil. */
const ROLES = {
    administrateur: {
        home: '/administration',
        title: 'Administrateur',
        service: 'Administration',
        onlyGroups: ['Administration'],
    },
    direction_generale: {
        home: '/dashboard',
        title: 'Directeur général',
        service: 'Direction générale',
    },
    direction_technique: {
        home: '/dashboard',
        title: 'Directeur technique',
        service: 'Direction technique',
    },
    service_achats_btp: {
        home: '/achats/a-valider',
        title: 'Responsable achats BTP',
        service: 'Achats BTP',
    },
    conducteur_travaux: {
        home: '/chantiers',
        title: 'Conducteur de travaux',
        service: 'Travaux',
    },
    chef_chantier: {
        home: '/chantiers',
        title: 'Chef de chantier',
        service: 'Travaux',
    },
    magasinier: {
        home: '/achats/receptions',
        title: 'Magasinier',
        service: 'Magasin',
    },
    comptabilite: {
        home: '/achats/factures',
        title: 'Comptable',
        service: 'Comptabilité',
    },
    finance: {
        home: '/ecrans/situation-caisse',
        title: 'Responsable financier',
        service: 'Finance',
    },
    ressources_humaines: {
        home: '/rh',
        title: 'Chargée des ressources humaines',
        service: 'Ressources humaines',
    },
    complet: {
        email: env.E2E_EMAIL,
        home: null,
        title: 'Testeur',
        service: null,
    },
};

function totp(secret) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const c of secret.toUpperCase().replace(/=+$/, '')) {
        bits += alphabet.indexOf(c).toString(2).padStart(5, '0');
    }
    const key = Buffer.from(bits.match(/.{8}/g).map((b) => parseInt(b, 2)));
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
    const h = createHmac('sha1', key).update(counter).digest();
    const o = h[h.length - 1] & 15;
    const n =
        ((h[o] & 0x7f) << 24) | (h[o + 1] << 16) | (h[o + 2] << 8) | h[o + 3];

    return String(n % 1_000_000).padStart(6, '0');
}

const aliases = { 'Tableau de bord': 'bonjour' };
const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
const SIDEBAR_BTN = '[data-sidebar="content"] [data-sidebar="menu-button"]';
const SUB_BTN = '[data-sidebar="menu-sub-button"]';

const browser = await chromium.launch();
const failures = [];
const summary = [];

async function runRole(name) {
    const spec = ROLES[name];
    const email = spec.email ?? `${local}.${name}@${domain}`;
    const ctx = await browser.newContext({
        viewport: { width: 1600, height: 900 },
    });
    const page = await ctx.newPage();
    let errors = [];
    let checked = 0;
    const fail = (where, msg) => {
        failures.push(`[${name}] ${where} → ${msg}`);
        console.log(`  ✗ [${name}] ${where} → ${msg}`);
    };

    page.on(
        'console',
        (m) => m.type() === 'error' && errors.push(m.text().slice(0, 200)),
    );
    page.on('pageerror', (e) => errors.push(`PAGEERROR ${e.message}`));
    page.on('response', (r) => {
        if (
            r.status() >= 400 &&
            r.request().resourceType() !== 'image' &&
            !r.url().includes('/build/')
        ) {
            errors.push(`HTTP ${r.status()} ${r.url().replace(BASE, '')}`);
        }
    });

    const settle = async () => {
        await page.waitForLoadState('networkidle').catch(() => {});
        await page.waitForTimeout(250);
    };
    const go = (path) =>
        page
            .goto(`${BASE}${path}`, { waitUntil: 'domcontentloaded' })
            .then(settle);
    const waitPath = (path) =>
        page
            .waitForURL((u) => u.pathname === path, {
                timeout: 30000,
                waitUntil: 'commit',
            })
            .catch(() => {});

    // Ouvre la catégorie du menu si elle ne l'est pas déjà (celle de la page courante l'est par défaut).
    const ensureOpen = async (label) => {
        const button = page.locator(SIDEBAR_BTN, { hasText: label }).first();
        const state = await button.evaluate((e) =>
            e.closest('li').getAttribute('data-state'),
        );
        if (state !== 'open') {
            await button.click();
            await page.waitForTimeout(250);
        }
    };

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
            const word =
                aliases[label] ??
                norm(label)
                    .split(/[^a-z0-9]+/)
                    .filter((w) => w.length >= 4)[0];
            if (word && !norm(h1).includes(norm(word).slice(0, 5)))
                problems.push(`titre « ${h1} » ≠ « ${label} »`);
        }
        if (
            (await page.evaluate(
                () => getComputedStyle(document.body).pointerEvents,
            )) === 'none'
        ) {
            problems.push('body pointer-events: none');
        }
        if (errors.length) problems.push(`console : ${errors.join(' | ')}`);
        errors = [];
        if (problems.length) fail(`${where} › ${label}`, problems.join(' ; '));
        else console.log(`  ✓ ${where} › ${label} — « ${h1} »`);
    }

    console.log(`\n=== ${name} (${email}) ===`);

    // --- Connexion (+ double authentification si le profil l'impose) -----------
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('#email', email);
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
        await page.waitForURL(
            (u) => !u.pathname.includes('two-factor-challenge'),
            { timeout: 90000, waitUntil: 'commit' },
        );
    }
    await settle();
    errors = [];

    // --- Page d'arrivée --------------------------------------------------------
    const landed = new URL(page.url()).pathname;
    if (landed.startsWith('/settings') || landed.startsWith('/user/'))
        fail('arrivée', `atterri sur ${landed}`);
    else if (spec.home && landed !== spec.home)
        fail('arrivée', `${landed} ≠ ${spec.home}`);
    else console.log(`  ✓ arrivée sur ${landed}`);

    // --- En-tête : poste, service, sans logo, fixe -----------------------------
    await go('/dashboard');
    const title = (
        await page
            .locator('[data-test="header-title"]')
            .innerText()
            .catch(() => '')
    ).trim();
    const service = (
        await page
            .locator('[data-test="header-service"]')
            .innerText()
            .catch(() => '')
    ).trim();
    if (title !== spec.title)
        fail('en-tête', `titre « ${title} » ≠ « ${spec.title} »`);
    if (spec.service && service !== spec.service)
        fail('en-tête', `service « ${service} » ≠ « ${spec.service} »`);
    if ((await page.locator('[data-test="legacy-header"] img').count()) > 0)
        fail('en-tête', 'un logo est encore présent');
    await page.evaluate(() => window.scrollTo(0, 600));
    await page.waitForTimeout(200);
    const top = await page
        .locator('[data-test="sticky-header"]')
        .evaluate((e) => Math.round(e.getBoundingClientRect().top));
    const scrolled = await page.evaluate(() => window.scrollY);
    if (scrolled > 0 && top !== 0)
        fail('en-tête', `non fixe au défilement (top=${top})`);
    console.log(
        `  ✓ en-tête « ${title} · ${service} »${scrolled > 0 ? ' (fixe au défilement)' : ''}`,
    );

    // --- Menu latéral : catégories, accordéon, entrées ------------------------
    const groupButtons = page.locator(SIDEBAR_BTN);
    const groupCount = await groupButtons.count();
    const groups = [];
    for (let i = 0; i < groupCount; i++)
        groups.push((await groupButtons.nth(i).innerText()).trim());
    if (groups.length === 0) fail('menu', 'aucune catégorie visible');
    if (spec.onlyGroups && groups.join('|') !== spec.onlyGroups.join('|')) {
        fail(
            'menu',
            `catégories « ${groups.join(' / ')} » ≠ « ${spec.onlyGroups.join(' / ')} »`,
        );
    }

    // Charte : barre latérale en bleu marine (#0b2a4f), exercices ouverts en liste.
    const sidebarBg = await page
        .locator('[data-sidebar="sidebar"]')
        .first()
        .evaluate((e) => getComputedStyle(e).backgroundColor);
    if (sidebarBg !== 'rgb(11, 42, 79)')
        fail('charte', `fond de la barre latérale ${sidebarBg} ≠ bleu marine`);
    const exercices = await page
        .locator('[data-test="exercice-select"] option')
        .evaluateAll((os) => os.map((o) => o.textContent.trim()));
    if (exercices.join(',') !== '2025,2026')
        fail('en-tête', `exercices proposés : ${exercices.join(',')}`);

    if (groups.length >= 1) {
        const open = (label) =>
            page.locator(SIDEBAR_BTN, { hasText: label }).first();
        if (
            (await open(groups[0]).evaluate((e) =>
                e.closest('li').getAttribute('data-state'),
            )) === 'open'
        ) {
            await open(groups[0]).click();
            await page.waitForTimeout(300);
        }
        await open(groups[0]).click();
        await page.waitForTimeout(300);
        const chevron = await open(groups[0])
            .locator('svg')
            .last()
            .evaluate((s) => s.getAttribute('class') ?? '');
        if (!chevron.includes('rotate-90'))
            fail('accordéon', 'le chevron ne pivote pas à l’ouverture');
        if ((await page.locator(`${SUB_BTN}:visible`).count()) === 0)
            fail('accordéon', 'le clic n’ouvre pas la catégorie');
        if (groups.length >= 2) {
            await open(groups[1]).click();
            await page.waitForTimeout(300);
            const opened = await page
                .locator('[data-sidebar="menu-item"][data-state="open"]')
                .count();
            if (opened !== 1)
                fail(
                    'accordéon',
                    `${opened} catégories ouvertes en même temps`,
                );
        }
        console.log(
            '  ✓ accordéon : ouverture, chevron, une seule catégorie ouverte',
        );
    }

    for (const group of groups) {
        await go('/dashboard');
        await ensureOpen(group);
        await page.waitForTimeout(250);
        const items = await page
            .locator(`${SUB_BTN}:visible`)
            .evaluateAll((as) =>
                as.map((a) => ({
                    label: a.textContent.trim(),
                    href: a.getAttribute('href'),
                })),
            );
        if (items.length === 0)
            fail('menu', `catégorie « ${group} » sans entrée`);
        for (const item of items) {
            await go('/dashboard');
            await ensureOpen(group);
            await page.waitForTimeout(250);
            await page.locator(`${SUB_BTN}[href="${item.href}"]`).click();
            await waitPath(item.href);
            await verify(`menu › ${group}`, item.label, item.href);
            const active = await page
                .locator(`${SUB_BTN}[href="${item.href}"]`)
                .first()
                .getAttribute('data-active');
            if (active !== 'true')
                fail(
                    `menu › ${group} › ${item.label}`,
                    'écran courant non mis en évidence',
                );
        }
        if (items[0]) {
            await go(items[0].href);
            const visible = await page
                .locator(`${SUB_BTN}[href="${items[0].href}"]`)
                .first()
                .isVisible();
            if (!visible)
                fail(
                    `menu › ${group}`,
                    'catégorie de la page courante non ouverte par défaut',
                );
        }
    }

    // --- Menu du haut ---------------------------------------------------------
    await go('/dashboard');
    const topLinks = await page
        .locator('nav[aria-label="Menu principal"] a[href]')
        .evaluateAll((as) =>
            as
                .filter((a) => a.getAttribute('href') !== '/notifications')
                .map((a) => ({
                    label: a.textContent.trim(),
                    href: a.getAttribute('href'),
                })),
        );
    for (const item of topLinks) {
        await go('/dashboard');
        await page
            .locator(`nav[aria-label="Menu principal"] a[href="${item.href}"]`)
            .click();
        await waitPath(item.href);
        await verify('menu du haut', item.label, item.href);
        const current = await page
            .locator(`nav[aria-label="Menu principal"] a[href="${item.href}"]`)
            .getAttribute('aria-current');
        if (current !== 'page')
            fail(
                `menu du haut › ${item.label}`,
                'entrée courante non mise en évidence',
            );
    }

    // --- Menu Fichier (ouverture, échap, clic) --------------------------------
    await go('/dashboard');
    const trigger = page.locator(
        'nav[aria-label="Menu principal"] button:has-text("Fichier")',
    );
    const hasFichier = (await trigger.count()) > 0; // sans droit sur un rapport, le menu Fichier n’est pas affiché
    if (hasFichier) await trigger.click();
    const fichier = hasFichier
        ? await page.locator('[role="menu"] a[href]').evaluateAll((as) =>
              as.map((a) => ({
                  label: a.textContent.trim(),
                  href: a.getAttribute('href'),
              })),
          )
        : [];
    await page.keyboard.press('Escape');
    await page.waitForTimeout(300);
    if (
        (await page.evaluate(
            () => getComputedStyle(document.body).pointerEvents,
        )) === 'none'
    ) {
        fail('Fichier', 'body pointer-events: none après fermeture du menu');
    }
    for (const item of fichier) {
        await go('/dashboard');
        await trigger.click();
        await page.locator(`[role="menu"] a[href="${item.href}"]`).click();
        await waitPath(item.href);
        await verify('Fichier', item.label, item.href);
    }

    // --- Cloche, puis menu utilisateur ----------------------------------------
    await go('/dashboard');
    await page.locator('[data-test="notifications-bell"]').click();
    await page
        .locator('[data-test="notifications-all"]')
        .waitFor({ timeout: 10000 });
    await page.locator('[data-test="notifications-all"]').click();
    await waitPath('/notifications');
    await verify('en-tête (cloche)', 'Notifications', '/notifications');
    await go('/dashboard');
    await page.locator('[data-sidebar="footer"] button').last().click();
    await page.keyboard.press('Escape');
    await page.waitForTimeout(300);
    await page.locator(SIDEBAR_BTN).first().click();
    await verify('après menu utilisateur', '', null);

    await ctx.close();
    summary.push(`${name} : ${checked} pages — ${groups.join(' / ')}`);
}

const wanted = process.argv.slice(2);
for (const name of wanted.length ? wanted : Object.keys(ROLES)) {
    try {
        await runRole(name);
    } catch (e) {
        failures.push(`[${name}] exception : ${e.message.split('\n')[0]}`);
        console.log(`  ✗ [${name}] exception : ${e.message.split('\n')[0]}`);
    }
}

await browser.close();
console.log('\n=== Résumé ===');
summary.forEach((s) => console.log(s));
console.log(`${failures.length} échec(s).`);
failures.forEach((f) => console.log(`  ✗ ${f}`));
process.exit(failures.length ? 1 : 0);
