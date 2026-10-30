/**
 * Génère le favicon Mega Challenge à partir du logo blanc existant :
 * public/favicon.svg, favicon.ico (32 px), apple-touch-icon.png (180 px).
 * Usage : docker compose exec -T laravel.test node e2e/favicon.mjs
 */
import fs from 'node:fs';
import { chromium } from 'playwright-core';

const root = new URL('../public/', import.meta.url);
const src = fs.readFileSync(
    new URL('brand/logo-mega-challenge-blanc.svg', root),
    'utf8',
);
const viewBox = src.match(/viewBox="([^"]+)"/)[1];
const inner = src
    .replace(/^[\s\S]*?<svg[^>]*>/, '')
    .replace(/<\/svg>\s*$/, '')
    .replace(/<title>[\s\S]*?<\/title>/, '');
const [, , w, h] = viewBox.split(/\s+/).map(Number);
const size = 64;
const pad = 7;
const iw = size - pad * 2;
const ih = (iw * h) / w;

const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}" width="${size}" height="${size}">
<rect width="${size}" height="${size}" rx="12" fill="#0057b5"/>
<svg x="${pad}" y="${(size - ih) / 2}" width="${iw}" height="${ih}" viewBox="${viewBox}">${inner}</svg>
</svg>
`;
fs.writeFileSync(new URL('favicon.svg', root), svg);

const browser = await chromium.launch();
async function png(px) {
    const page = await browser.newPage({ viewport: { width: px, height: px } });
    await page.setContent(
        `<body style="margin:0;background:transparent">${svg.replace(/width="64" height="64"/, `width="${px}" height="${px}"`)}</body>`,
    );
    const buf = await page.screenshot({
        omitBackground: true,
        clip: { x: 0, y: 0, width: px, height: px },
    });
    await page.close();

    return buf;
}
const p32 = await png(32);
const p180 = await png(180);
fs.writeFileSync(new URL('apple-touch-icon.png', root), p180);

// favicon.ico contenant le PNG 32 px.
const header = Buffer.alloc(22);
header.writeUInt16LE(0, 0);
header.writeUInt16LE(1, 2);
header.writeUInt16LE(1, 4);
header.writeUInt8(32, 6);
header.writeUInt8(32, 7);
header.writeUInt16LE(1, 10);
header.writeUInt16LE(32, 12);
header.writeUInt32LE(p32.length, 14);
header.writeUInt32LE(22, 18);
fs.writeFileSync(new URL('favicon.ico', root), Buffer.concat([header, p32]));
await browser.close();
console.log(
    'favicon.svg, favicon.ico (32 px), apple-touch-icon.png (180 px) générés',
);
