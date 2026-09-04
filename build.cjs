const fs = require('node:fs');
const path = require('node:path');
const root = __dirname;
const publicFiles = ['index.html', 'operations.html', 'style.css', 'app.js', 'logo.jpg', 'project-showcase-v2.png', 'bullmoon-launch.webp', 'bullmoon-event.webp', 'og.png'];
fs.mkdirSync(path.join(root, 'dist'), { recursive: true });
for (const file of publicFiles) {
  fs.copyFileSync(path.join(root, 'montopia', file), path.join(root, 'dist', file));
}
console.log(`Built ${publicFiles.length} public files into dist.`);
