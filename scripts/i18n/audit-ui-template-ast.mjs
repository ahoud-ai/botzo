#!/usr/bin/env node

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { parse as parseSfc } from '@vue/compiler-sfc';
import { parse as parseTemplateAst } from '@vue/compiler-dom';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootPath = path.resolve(__dirname, '..', '..');

const options = parseCliOptions(process.argv.slice(2));
const format = String(options.format || 'json').toLowerCase();
const outputPath = options.out ? String(options.out) : null;
const failOnTemplateUntranslated = toBool(options['fail-on-template-untranslated']);
const scopes = expandScopes(String(options.scope || 'admin,user'));

const allowlist = loadPhpArrayStrings(path.resolve(__dirname, 'allowlist_terms.php')).map((term) =>
  term.toLowerCase()
);
const excludedPaths = loadPhpArrayStrings(path.resolve(__dirname, 'excluded_paths.php')).map(normalizePath);

const scopeMap = {
  admin: ['resources/js/Pages/Admin'],
  user: ['resources/js/Pages/User'],
  public: ['resources/js/Pages/Frontend'],
  auth: ['resources/js/Pages/Auth'],
};

let scanPaths = [];
for (const scope of scopes) {
  if (scopeMap[scope]) {
    scanPaths = scanPaths.concat(scopeMap[scope]);
  }
}

if (scopes.includes('admin') || scopes.includes('user')) {
  scanPaths.push('resources/js/Components');
}

scanPaths = Array.from(new Set(scanPaths.map(normalizePath)));

const files = collectVueFiles(rootPath, scanPaths, excludedPaths);
const issues = [];

for (const filePath of files) {
  const source = fs.readFileSync(filePath, 'utf8');
  const relativePath = normalizePath(path.relative(rootPath, filePath));

  const parsed = parseSfc(source, { filename: filePath });
  const templateBlock = parsed?.descriptor?.template;
  if (!templateBlock?.content) {
    continue;
  }

  let ast;
  try {
    ast = parseTemplateAst(templateBlock.content, { comments: false });
  } catch (_error) {
    continue;
  }

  const templateStartLine = Number(templateBlock.loc?.start?.line || 1);
  walkAst(ast, null, (node, parentTag) => {
    if (node.type === 2) {
      const text = normalizeInlineText(node.content || '');
      if (!shouldCaptureText(text)) {
        return;
      }

      const type = isAllowlisted(text, allowlist) ? 'template_allowed_term' : 'template_text_untranslated';
      issues.push({
        type,
        category: parentTag === 'option' ? 'option_text' : 'template_text',
        text,
        file: relativePath,
        line: Number(node.loc?.start?.line || 1) + templateStartLine - 1,
        snippet: compactSnippet(text),
      });
      return;
    }

    if (node.type === 1) {
      for (const prop of node.props || []) {
        if (prop.type !== 6) {
          continue;
        }

        const attrName = String(prop.name || '').toLowerCase();
        if (!['placeholder', 'title', 'label', 'aria-label', 'alt'].includes(attrName)) {
          continue;
        }

        const attrValue = normalizeInlineText(prop.value?.content || '');
        if (!shouldCaptureText(attrValue)) {
          continue;
        }

        const type = isAllowlisted(attrValue, allowlist) ? 'template_allowed_term' : 'template_text_untranslated';
        issues.push({
          type,
          category: `attribute_${attrName}`,
          text: attrValue,
          file: relativePath,
          line: Number(prop.loc?.start?.line || 1) + templateStartLine - 1,
          snippet: compactSnippet(`${attrName}="${attrValue}"`),
        });
      }
    }
  });
}

const templateIssues = deduplicateIssues(issues);
const report = {
  generated_at: new Date().toISOString(),
  scope: scopes,
  scan_paths: scanPaths,
  excluded_paths: excludedPaths,
  summary: {
    files_scanned: files.length,
    template_text_untranslated: templateIssues.filter((item) => item.type === 'template_text_untranslated').length,
    template_allowed_term: templateIssues.filter((item) => item.type === 'template_allowed_term').length,
  },
  template_issues: templateIssues,
  exit_policy: {
    fail_on_template_untranslated: failOnTemplateUntranslated,
  },
};

const rendered =
  format === 'markdown'
    ? renderMarkdownReport(report)
    : JSON.stringify(report, null, 2);

if (outputPath) {
  const fullOutputPath = path.isAbsolute(outputPath) ? outputPath : path.resolve(rootPath, outputPath);
  const outputDir = path.dirname(fullOutputPath);
  if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
  }
  fs.writeFileSync(fullOutputPath, rendered, 'utf8');
} else {
  process.stdout.write(`${rendered}\n`);
}

const shouldFail =
  failOnTemplateUntranslated && report.summary.template_text_untranslated > 0;
process.exit(shouldFail ? 1 : 0);

function parseCliOptions(args) {
  const result = {};
  for (const arg of args) {
    if (!arg.startsWith('--')) {
      continue;
    }

    const raw = arg.slice(2);
    const [key, value] = raw.includes('=') ? raw.split(/=(.*)/s, 2) : [raw, '1'];
    result[key] = value;
  }
  return result;
}

function toBool(value) {
  if (value == null) {
    return false;
  }
  return ['1', 'true', 'yes', 'on'].includes(String(value).trim().toLowerCase());
}

function expandScopes(rawScopes) {
  const scopeAliases = { all: ['admin', 'user', 'public', 'auth'] };
  const list = rawScopes
    .split(',')
    .map((item) => item.trim().toLowerCase())
    .filter(Boolean);

  const expanded = [];
  for (const scope of list.length ? list : ['admin', 'user']) {
    if (scopeAliases[scope]) {
      expanded.push(...scopeAliases[scope]);
      continue;
    }
    expanded.push(scope);
  }
  return Array.from(new Set(expanded));
}

function loadPhpArrayStrings(filePath) {
  if (!fs.existsSync(filePath)) {
    return [];
  }
  const content = fs.readFileSync(filePath, 'utf8');
  const results = [];
  const regex = /'([^']+)'/g;
  let match;
  while ((match = regex.exec(content)) !== null) {
    results.push(match[1]);
  }
  return results;
}

function normalizePath(value) {
  return String(value).replace(/\\/g, '/');
}

function collectVueFiles(basePath, scanPaths, excluded) {
  const files = [];

  for (const scanPath of scanPaths) {
    const absolutePath = path.resolve(basePath, scanPath);
    if (!fs.existsSync(absolutePath) || !fs.statSync(absolutePath).isDirectory()) {
      continue;
    }

    for (const filePath of walkDirectory(absolutePath)) {
      if (!filePath.endsWith('.vue')) {
        continue;
      }
      const relative = normalizePath(path.relative(basePath, filePath));
      if (isExcluded(relative, excluded)) {
        continue;
      }
      files.push(filePath);
    }
  }

  files.sort();
  return Array.from(new Set(files));
}

function* walkDirectory(dir) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const entry of entries) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      yield* walkDirectory(fullPath);
      continue;
    }
    if (entry.isFile()) {
      yield fullPath;
    }
  }
}

function isExcluded(relativePath, excluded) {
  return excluded.some((item) => relativePath.startsWith(item));
}

function walkAst(node, parentTag, visitor) {
  visitor(node, parentTag);

  if (Array.isArray(node.children)) {
    const nextParent = node.type === 1 ? String(node.tag || '').toLowerCase() : parentTag;
    for (const child of node.children) {
      walkAst(child, nextParent, visitor);
    }
  }

  if (Array.isArray(node.branches)) {
    for (const branch of node.branches) {
      walkAst(branch, parentTag, visitor);
    }
  }
}

function shouldCaptureText(text) {
  if (!text) {
    return false;
  }
  if (/^\/[a-z0-9/_-]+$/i.test(text)) {
    return false;
  }
  if (/^[\d\W_]+$/u.test(text)) {
    return false;
  }
  if (text.includes('{{') || text.includes('}}')) {
    return false;
  }
  if (/^\$t\(/u.test(text) || /^(trans|__)\(/u.test(text)) {
    return false;
  }
  return /\p{L}/u.test(text);
}

function normalizeInlineText(value) {
  return String(value).replace(/\s+/g, ' ').trim();
}

function compactSnippet(value) {
  const text = normalizeInlineText(value);
  return text.length <= 160 ? text : `${text.slice(0, 157)}...`;
}

function isAllowlisted(text, allowlist) {
  return allowlist.includes(String(text).toLowerCase());
}

function deduplicateIssues(items) {
  const map = new Map();
  for (const item of items) {
    const key = [
      item.type,
      item.category,
      item.file,
      item.line,
      item.text,
    ].join('|');
    if (!map.has(key)) {
      map.set(key, item);
    }
  }
  return Array.from(map.values());
}

function renderMarkdownReport(report) {
  const lines = [];
  lines.push('# Template AST I18n Audit Report');
  lines.push('');
  lines.push(`- Generated: ${report.generated_at}`);
  lines.push(`- Scope: ${(report.scope || []).join(', ')}`);
  lines.push('');
  lines.push('## Summary');
  lines.push('');
  lines.push('| Metric | Value |');
  lines.push('| --- | ---: |');
  lines.push(`| files_scanned | ${report.summary.files_scanned} |`);
  lines.push(`| template_text_untranslated | ${report.summary.template_text_untranslated} |`);
  lines.push(`| template_allowed_term | ${report.summary.template_allowed_term} |`);
  lines.push('');
  lines.push('## Template Untranslated');
  lines.push('');

  for (const issue of report.template_issues || []) {
    if (issue.type !== 'template_text_untranslated') {
      continue;
    }
    lines.push(`- \`${issue.text}\` at \`${issue.file}:${issue.line}\``);
  }

  return lines.join('\n');
}

