import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(new URL('../../../../', import.meta.url).pathname);
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

test('theme contexts keep the panel shell isolated from the public site', () => {
  const app = read('payload/app.php');
  const admin = read('payload/Controller/AdminController.php');
  const publicController = read('payload/Controller/PublicContentController.php');
  const api = read('payload/Controller/Api/ThemeRuntimeApiController.php');
  const vue = read('payload/theme/cms-admin/src/pages/appearance/page-appearance.vue');
  const runtime = read('payload/theme/cms-admin/runtime/appearance.mjs');

  assert.match(app, /'theme-context'\s*=>\s*'panel'/);
  assert.match(app, /'panel'\s*=>\s*\['theme'\s*=>\s*'cms-admin'\]/);
  assert.match(app, /'site'\s*=>\s*\['theme'\s*=>\s*'cms-admin'\]/);
  assert.match(admin, /stack\(\$themeDefinition->package,\s*\$context\)/);
  assert.match(admin, /\$context\s*=\s*\$themeDefinition->package === \$siteThemePackage \? 'site' : null/);
  assert.equal((publicController.match(/\$context->siteId,\s*'site',/g) || []).length, 3);
  assert.match(api, /\$context=\$package==='com_pinoox_cms'\?'site':null/);
  assert.match(api, /\$context===null\|\|\$context===''\)\&\&\$package==='com_pinoox_cms'/);
  assert.match(vue, /theme\.package===sitePackage\?'site':null/);
  assert.match(runtime, /x\.package==='com_pinoox_cms'\?'site':null/);
});
