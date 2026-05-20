import { existsSync, mkdirSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const repoRoot = process.cwd();
const langDir = path.join(repoRoot, 'lang');

if (!existsSync(langDir)) {
    process.exit(0);
}

const locales = readdirSync(langDir, { withFileTypes: true })
    .filter((entry) => entry.isDirectory())
    .map((entry) => entry.name)
    .filter((locale) => {
        const localeDir = path.join(langDir, locale);

        return readdirSync(localeDir).some((file) => {
            const fullPath = path.join(localeDir, file);
            return statSync(fullPath).isFile() && fullPath.endsWith('.php');
        });
    });

for (const locale of locales) {
    const placeholderPath = path.join(langDir, `php_${locale}.json`);

    if (!existsSync(placeholderPath)) {
        writeFileSync(placeholderPath, '{}\n', 'utf8');
    }
}
