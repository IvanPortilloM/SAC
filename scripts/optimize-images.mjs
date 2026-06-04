// scripts/optimize-images.mjs
// Convierte las imágenes de assets/img/slides a WebP (manteniendo el .jpg como
// respaldo para navegadores viejos). Ejecuta:  node scripts/optimize-images.mjs
import sharp from 'sharp';
import { readdir, stat } from 'fs/promises';
import path from 'path';

const dir = 'assets/img/slides';
const files = await readdir(dir);
let savedTotal = 0;

for (const f of files) {
  if (!/\.(jpe?g|png)$/i.test(f)) continue;
  const input = path.join(dir, f);
  const output = path.join(dir, f.replace(/\.(jpe?g|png)$/i, '.webp'));
  const meta = await sharp(input).metadata();

  await sharp(input)
    .resize({ width: Math.min(meta.width, 1600), withoutEnlargement: true })
    .webp({ quality: 80 })
    .toFile(output);

  const before = (await stat(input)).size;
  const after = (await stat(output)).size;
  savedTotal += before - after;
  const pct = Math.round((1 - after / before) * 100);
  console.log(
    `${f}  ${(before / 1024).toFixed(0)}KB -> ${(after / 1024).toFixed(0)}KB  (-${pct}%)`
  );
}

console.log(`\nAhorro total: ${(savedTotal / 1024).toFixed(0)} KB`);
