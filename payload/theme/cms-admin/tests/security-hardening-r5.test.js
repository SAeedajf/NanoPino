import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))
const root = resolve(themeRoot, '../..')
const read = (p) => readFileSync(resolve(root, p), 'utf8')

test('R5 content ownership and author assignment are fail-closed', () => {
  const caps = read('Cms/Capability/CoreCapabilities.php')
  const service = read('Cms/Content/ContentService.php')
  const revisions = read('Cms/Revision/RevisionService.php')
  const roles = read('Cms/Identity/CoreRoleTemplates.php')
  const search = read('Cms/Search/SearchService.php')

  assert.match(caps, /content\.manage_others/)
  assert.match(caps, /content\.assign_author/)
  assert.match(service, /resolveAuthorId/)
  assert.match(service, /content\.assign_author/)
  assert.match(service, /Content author does not exist/)
  assert.match(service, /authorizeOwnedContent/)
  assert.match(service, /authorId: \$actorId/)
  assert.match(revisions, /content\.manage_others/)
  assert.match(search, /content\.manage_others/)

  const authorBlock = roles.match(/'cms_author'[\s\S]*?\),\n\s*new RoleTemplateDefinition/)?.[0] || ''
  assert.doesNotMatch(authorBlock, /content\.manage_others/)
  assert.doesNotMatch(authorBlock, /content\.assign_author/)
  assert.match(roles, /'cms_editor'[\s\S]*?'content\.\*'/)
})

test('R5 request integrity does not trust same-site sibling origins', () => {
  const flow = read('Cms/Security/Http/CmsRequestIntegrityFlow.php')
  assert.match(flow, /\$fetchSite==='same-origin'/)
  assert.doesNotMatch(flow, /same-origin','same-site/)
})

test('R5 security evidence keeps unresolved runtime controls explicit', () => {
  const evidence = JSON.parse(read('resources/release/security-hardening-r5-v1.json'))
  assert.equal(evidence.controls.content_ownership.status, 'pass')
  assert.equal(evidence.controls.csrf_same_origin.same_site_trusted, false)
  assert.equal(evidence.controls.csp.status, 'report_only')
  assert.equal(evidence.controls.ssrf_remote_transport.status, 'unbound')
  assert.equal(evidence.controls.safe_mode_boot_order.status, 'framework_integration_required')
})
