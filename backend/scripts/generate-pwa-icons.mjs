import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';
import toIco from 'to-ico';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const logoSvg = await readFile(path.join(root, 'public/logo.svg'));
const iconsDir = path.join(root, 'public/icons');

await mkdir(iconsDir, { recursive: true });

async function renderIcon(size, outputName, { maskable = false } = {}) {
    if (!maskable) {
        await sharp(logoSvg).resize(size, size).png().toFile(path.join(iconsDir, outputName));

        return;
    }

    const inset = Math.round(size * 0.1);
    const inner = size - inset * 2;
    const logo = await sharp(logoSvg).resize(inner, inner).png().toBuffer();

    await sharp({
        create: {
            width: size,
            height: size,
            channels: 4,
            background: '#0b0f14',
        },
    })
        .composite([{ input: logo, gravity: 'center' }])
        .png()
        .toFile(path.join(iconsDir, outputName));
}

await renderIcon(192, 'icon-192.png');
await renderIcon(512, 'icon-512.png');
await renderIcon(512, 'icon-512-maskable.png', { maskable: true });

const favicon16 = await sharp(logoSvg).resize(16, 16).png().toBuffer();
const favicon32 = await sharp(logoSvg).resize(32, 32).png().toBuffer();
await writeFile(path.join(root, 'public/favicon.ico'), await toIco([favicon16, favicon32]));

console.log('PWA icons generated from public/logo.svg');
