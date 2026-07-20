/**
 * Render the WordPress.org asset set from the sources in assets/source/.
 *
 * The images are build output, not hand-pixels: every one is produced from
 * HTML/SVG that uses the identity tokens, so a palette change is one edit and a
 * rebuild rather than a redraw. Dimensions are exactly what wordpress.org
 * expects, and the shots are taken at deviceScaleFactor 1 so nothing is
 * resampled.
 *
 * Fonts are fetched into assets/.fonts-cache/ on first run rather than
 * committed: they are unmodified upstream OFL releases, and vendoring several
 * megabytes of binary into the repository to regenerate a handful of PNGs is a
 * poor trade. Sources and licences are listed in FONTS below.
 *
 *   node assets/build-assets.mjs
 *
 * Requires Playwright's chromium:
 *   corepack pnpm --ignore-workspace exec playwright install chromium
 */

import { chromium } from 'playwright';
import { mkdir, writeFile, readFile, access } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here = path.dirname(fileURLToPath(import.meta.url));
const SOURCE = path.join(here, 'source');
const CACHE = path.join(here, '.fonts-cache');

/** Upstream font releases. All SIL Open Font License 1.1. */
const FONTS = [
  {
    file: 'Archivo.ttf',
    url: 'https://github.com/google/fonts/raw/main/ofl/archivo/Archivo%5Bwdth%2Cwght%5D.ttf',
  },
  {
    file: 'IBMPlexSans.ttf',
    url: 'https://github.com/google/fonts/raw/main/ofl/ibmplexsans/IBMPlexSans%5Bwdth%2Cwght%5D.ttf',
  },
  {
    file: 'IBMPlexMono-Regular.ttf',
    url: 'https://github.com/google/fonts/raw/main/ofl/ibmplexmono/IBMPlexMono-Regular.ttf',
  },
  {
    file: 'IBMPlexMono-SemiBold.ttf',
    url: 'https://github.com/google/fonts/raw/main/ofl/ibmplexmono/IBMPlexMono-SemiBold.ttf',
  },
];

/** The exact asset set wordpress.org reads, with its required dimensions. */
const TARGETS = [
  { source: 'icon.html', out: 'icon-128x128.png', width: 128, height: 128 },
  { source: 'icon.html', out: 'icon-256x256.png', width: 256, height: 256 },
  { source: 'banner.html', out: 'banner-772x250.png', width: 772, height: 250 },
  { source: 'banner.html', out: 'banner-1544x500.png', width: 1544, height: 500 },
  { source: 'screenshot-1.html', out: 'screenshot-1.png', width: 1280, height: 800 },
  { source: 'screenshot-2.html', out: 'screenshot-2.png', width: 1280, height: 800 },
];

async function ensureFonts() {
  await mkdir(path.join(SOURCE, 'fonts'), { recursive: true });
  await mkdir(CACHE, { recursive: true });

  for (const font of FONTS) {
    const cached = path.join(CACHE, font.file);
    const linked = path.join(SOURCE, 'fonts', font.file);
    try {
      await access(cached);
    } catch {
      process.stdout.write(`fetching ${font.file}\n`);
      const response = await fetch(font.url);
      if (!response.ok) {
        throw new Error(`Cannot fetch ${font.file}: HTTP ${response.status}`);
      }
      await writeFile(cached, Buffer.from(await response.arrayBuffer()));
    }
    await writeFile(linked, await readFile(cached));
  }
}

async function render() {
  const browser = await chromium.launch();

  for (const target of TARGETS) {
    const page = await browser.newPage({
      viewport: { width: target.width, height: target.height },
      deviceScaleFactor: 1,
    });

    await page.goto(`file://${path.join(SOURCE, target.source)}?w=${target.width}`);
    await page.evaluate(() => document.fonts.ready);

    await page.screenshot({ path: path.join(here, target.out) });
    await page.close();
    process.stdout.write(`${target.out}  ${target.width}x${target.height}\n`);
  }

  await browser.close();
}

await ensureFonts();
await render();
