import { cpSync, existsSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

// Only the static demo needs the README screenshots inside the exported site.
if (process.env.NEXT_PUBLIC_DEMO_MODE !== '1') {
  process.exit(0);
}

const here = dirname(fileURLToPath(import.meta.url));
const frontend = dirname(here);
const out = join(frontend, 'out', 'screenshots');
const files = [
  'dashboard.webp',
  'conflicts.webp',
  'staff.webp',
  'preferences.webp',
  'statistics.webp',
  'login.webp',
  'demo.gif',
  'ci-success.webp',
];

mkdirSync(out, { recursive: true });

let copied = 0;
for (const file of files) {
  const src = join(frontend, '..', 'docs', 'screenshots', file);
  if (existsSync(src)) {
    cpSync(src, join(out, file));
    copied += 1;
  }
}

console.log(`copied ${copied} screenshot assets to out/screenshots`);
