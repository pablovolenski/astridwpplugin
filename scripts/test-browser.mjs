import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
let chromium;
try { ({ chromium } = require('playwright')); }
catch {
  const bundle = path.join(os.homedir(), '.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/package.json');
  ({ chromium } = createRequire(bundle)('playwright'));
}
const browser = await chromium.launch({ headless: true, channel: 'msedge' });
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
const errors = [];
page.on('pageerror', error => errors.push(error.message));
const url = 'http://127.0.0.1:9400';
const results = [];
const check = (condition, description) => { assert.ok(condition, description); results.push(description); };
const roots = page.locator('[data-astrid]');

async function fillForm(root, name) {
  await root.locator('input[name="name"]').fill(name);
  await root.locator('input[name="phone"]').fill('+43 555 123');
  await root.locator('input[name="email"]').fill('browser@example.org');
  await root.locator('input[name="consent"]').check();
}

try {
  await page.goto(url + '/astrid-preview.html');
  await page.waitForSelector('[data-astrid-ready]');
  const popup = roots.nth(0), below = roots.nth(1), always = roots.nth(2);
  await popup.locator('.astrid-trigger').click();
  check(await popup.locator('dialog').evaluate(node => node.open), 'Native popup opens');
  check(await popup.locator('input[name="name"]').evaluate(node => node === document.activeElement), 'Opening focuses visible name field');
  await page.keyboard.press('Shift+Tab');
  await page.keyboard.press('Tab');
  check(await popup.locator('dialog').evaluate(node => node.contains(document.activeElement)), 'Native dialog contains keyboard focus');
  await page.keyboard.press('Escape');
  check(!await popup.locator('dialog').evaluate(node => node.open), 'Escape closes popup');
  check(await popup.locator('.astrid-trigger').evaluate(node => node === document.activeElement), 'Closing restores button focus');
  await popup.locator('.astrid-trigger').click();
  await popup.locator('.astrid-submit').click();
  check(await popup.locator('.astrid-status').innerText() === 'Complete the required fields', 'Missing fields show inline validation');
  await fillForm(popup, 'Popup Person');
  await popup.locator('.astrid-submit').click();
  await popup.locator('.astrid-success').waitFor({ state: 'visible' });
  check(!await popup.locator('.astrid-form-fields').isVisible(), 'Successful registration replaces form');
  await popup.locator('.astrid-close').click();
  await popup.locator('.astrid-trigger').click();
  check(await popup.locator('.astrid-form-fields').isVisible() && !await popup.locator('.astrid-success').isVisible(), 'Reopening clears previous success');
  check((await popup.locator('.astrid-submit').innerText()) === 'Send registration', 'Submit label resets after success');
  await popup.locator('.astrid-close').click();

  await below.locator('.astrid-trigger').click();
  check(!await below.locator('.astrid-panel').isVisible(), 'Options required before revealing form');
  check((await below.locator('.astrid-option-status').innerText()).includes('choose an option'), 'Missing-option message is visible');
  await below.locator('select').selectOption('0');
  await below.locator('.astrid-trigger').click();
  check(await below.locator('.astrid-panel').isVisible(), 'Below-button form opens with selected option');
  await fillForm(below, 'Options Person');
  await below.locator('.astrid-submit').click();
  await below.locator('.astrid-success').waitFor({ state: 'visible' });
  check(await always.locator('.astrid-form-fields').isVisible(), 'Separate static form is unaffected');
  await below.locator('.astrid-restart').click();
  check((await below.locator('select').inputValue()) === '0', 'Restart preserves selected activity option');

  await fillForm(always, 'Static Person');
  await page.route('**/wp-json/astrid/v1/register', route => route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ code: 'astrid_mail', message: 'Test mail transport failed' }) }));
  await always.locator('.astrid-submit').click();
  await always.locator('.astrid-status').filter({ hasText: 'Test mail transport failed' }).waitFor();
  check(!await always.locator('.astrid-submit').isDisabled(), 'Mail errors restore submit button');
  check((await always.locator('input[name="name"]').inputValue()) === 'Static Person', 'Mail error preserves participant details for retry');
  await page.unroute('**/wp-json/astrid/v1/register');
  await always.locator('.astrid-submit').click();
  await always.locator('.astrid-success').waitFor({ state: 'visible' });
  await always.locator('.astrid-restart').click();

  await page.setViewportSize({ width: 390, height: 844 });
  await popup.locator('.astrid-trigger').click();
  const rect = await popup.locator('dialog').boundingBox();
  check(rect.x >= 0 && rect.x + rect.width <= 390, 'Popup fits mobile viewport');
  await page.evaluate(() => document.getAnimations().forEach(animation => animation.finish()));
  await page.screenshot({ path: 'artifacts/astrid-mobile.png', fullPage: true });
  await popup.locator('.astrid-close').click();
  await page.setViewportSize({ width: 1280, height: 900 });
  if (!await below.locator('.astrid-panel').isVisible()) await below.locator('.astrid-trigger').click();
  await page.screenshot({ path: 'artifacts/astrid-desktop.png', fullPage: true });

  // Verify the actual WordPress editor loads both native blocks.
  await page.goto(url + '/wp-login.php');
  await page.locator('#user_login').fill('admin');
  await page.locator('#user_pass').fill('astrid-test-password');
  await page.locator('#wp-submit').click();
  await page.waitForURL('**/wp-admin/**');
  const fixture = JSON.parse(fs.readFileSync('artifacts/wordpress-checks.json', 'utf8'));
  await page.goto(url + '/wp-admin/post.php?post=' + fixture.postId + '&action=edit');
  await page.waitForFunction(() => window.wp && wp.blocks && wp.blocks.getBlockType('astrid/registration'));
  const editorData = await page.evaluate(() => ({
    single: !!wp.blocks.getBlockType('astrid/registration'),
    multiple: !!wp.blocks.getBlockType('astrid/multiple-options'),
    blocks: wp.data.select('core/block-editor').getBlocks()
  }));
  check(editorData.single && editorData.multiple, 'Both Astrid blocks load in Gutenberg');
  // The API can register before initial post hydration; wait for saved Group blocks.
  await page.waitForFunction(() => wp.data.select('core/block-editor').getBlocks().some(block => block.name === 'core/group'));
  const detected = await page.locator('.astrid-editor-context').allTextContents();
  // API v3 may render inside the Gutenberg iframe.
  if (!detected.length) {
    const frame = page.frameLocator('iframe[name="editor-canvas"]');
    await frame.locator('.astrid-editor-context').first().waitFor();
    detected.push(...await frame.locator('.astrid-editor-context').allTextContents());
  }
  check(detected.some(text => text.includes('Course Alpha & friends')) && detected.some(text => text.includes('Course Beta')), 'Gutenberg previews detect correct Group headings');
  await page.goto(url + '/wp-admin/admin.php?page=astrid');
  await page.locator('#astrid-button_style').selectOption('custom');
  await page.locator('#astrid-button_background').fill('#123456');
  await page.locator('#astrid-form_intro').fill('Welcome to {activity}.');
  await page.locator('#submit').click();
  await page.waitForLoadState('domcontentloaded');
  check((await page.locator('#astrid-form_intro').inputValue()) === 'Welcome to {activity}.', 'Dashboard saves edited form copy');
  check((await page.locator('#astrid-button_style').inputValue()) === 'custom', 'Dashboard saves button appearance');
  await page.goto(url + '/?page_id=' + fixture.postId);
  await page.locator('[data-astrid]').first().waitFor();
  const styled = page.locator('[data-astrid]').first();
  check(await styled.locator('.astrid-trigger').evaluate(node => getComputedStyle(node).backgroundColor === 'rgb(18, 52, 86)'), 'Custom button style applied on published WordPress page');
  await styled.locator('.astrid-trigger').click();
  check((await styled.locator('.astrid-intro').innerText()) === 'Welcome to Course Alpha & friends.', 'Edited copy substitutes activity on published page');
  check(errors.length === 0, 'No browser JavaScript errors');
  fs.writeFileSync('artifacts/browser-checks.json', JSON.stringify({ checks: results, errors }, null, 2));
  console.log(`Browser integration: ${results.length} checks passed.`);
} finally { await browser.close(); }
