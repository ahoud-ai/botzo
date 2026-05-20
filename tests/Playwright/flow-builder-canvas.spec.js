/* global process */

import { expect, test } from '@playwright/test';

const EMAIL = process.env.PLAYWRIGHT_FLOW_BUILDER_EMAIL || '';
const PASSWORD = process.env.PLAYWRIGHT_FLOW_BUILDER_PASSWORD || '';
const FLOW_URL = process.env.PLAYWRIGHT_FLOW_BUILDER_URL || '';
const NODE_ID = process.env.PLAYWRIGHT_FLOW_NODE_ID || '';

const hasHarnessConfig = Boolean(EMAIL && PASSWORD && FLOW_URL);

const openWorkspaceIfNeeded = async (page) => {
    if (!page.url().includes('/select-organization')) {
        return;
    }

    const openWorkspaceButton = page.getByRole('button', {
        name: /فتح مساحة العمل|open workspace/i,
    }).first();

    await openWorkspaceButton.waitFor({ state: 'visible', timeout: 15000 });
    await Promise.all([
        page.waitForLoadState('networkidle'),
        openWorkspaceButton.click(),
    ]);
};

const resolveFlowUrl = (baseURL = '') => {
    if (/^https?:\/\//i.test(FLOW_URL)) {
        return FLOW_URL;
    }

    if (!baseURL) {
        return FLOW_URL;
    }

    return new URL(FLOW_URL, baseURL).toString();
};

const resolveUsableNodeLocator = async (page) => {
    const nodes = page.locator('.vue-flow__node[data-id]:visible').filter({
        has: page.locator('[data-flow-node-card="true"]'),
    });
    const total = await nodes.count();

    for (let index = 0; index < total; index += 1) {
        const node = nodes.nth(index);
        const box = await node.boundingBox();
        const nodeId = await node.getAttribute('data-id');
        const expandButtonCount = await node.locator('[data-flow-node-expand="true"]').count();

        if (!box) {
            continue;
        }

        const centerX = box.x + (box.width / 2);
        const centerY = box.y + Math.min(box.height / 2, 48);
        const withinViewport = centerX > 48 && centerY > 48 && centerX < 1552 && centerY < 1052;

        if (withinViewport && expandButtonCount > 0 && nodeId !== 'trigger-1') {
            return node;
        }
    }

    return nodes.first();
};

const dragNodeBy = async (page, dragSurface, deltaX, deltaY) => {
    const box = await dragSurface.boundingBox();

    expect(box).not.toBeNull();

    const startX = box.x + (box.width / 2);
    const startY = box.y + (box.height / 2);

    await page.mouse.move(startX, startY);
    await page.mouse.down();
    await page.mouse.move(startX + deltaX, startY + deltaY, { steps: 12 });
    await page.mouse.up();
};

const dragNodeFromOffset = async (page, dragSurface, offsetX, offsetY, deltaX, deltaY) => {
    const box = await dragSurface.boundingBox();

    expect(box).not.toBeNull();

    const startX = box.x + offsetX;
    const startY = box.y + offsetY;

    await page.mouse.move(startX, startY);
    await page.mouse.down();
    await page.mouse.move(startX + deltaX, startY + deltaY, { steps: 12 });
    await page.mouse.up();
};

test.describe('Flow Builder canvas interactions', () => {
    test.skip(!hasHarnessConfig, 'Set PLAYWRIGHT_FLOW_BUILDER_EMAIL, PLAYWRIGHT_FLOW_BUILDER_PASSWORD, and PLAYWRIGHT_FLOW_BUILDER_URL to run this suite.');

    test('node drag remains responsive after expand and collapse', async ({ page, baseURL }) => {
        await page.goto('/login', { waitUntil: 'domcontentloaded' });

        await page.locator('input[type="email"]').first().fill(EMAIL);
        await page.locator('input[type="password"]').first().fill(PASSWORD);

        await Promise.all([
            page.waitForLoadState('networkidle'),
            page.locator('form button[type="submit"]').first().click(),
        ]);

        await openWorkspaceIfNeeded(page);

        await page.goto(resolveFlowUrl(baseURL), { waitUntil: 'networkidle' });
        await openWorkspaceIfNeeded(page);

        const targetNode = NODE_ID
            ? page.locator(`.vue-flow__node[data-id="${NODE_ID}"]`).first()
            : await resolveUsableNodeLocator(page);

        await targetNode.waitFor({ state: 'visible', timeout: 20000 });

        const card = targetNode.locator('[data-flow-node-card="true"]').first();
        const expandButton = targetNode.locator('[data-flow-node-expand="true"]').first();
        const dragSurface = card.locator('[data-flow-node-summary="true"]').first();

        await expect(card.locator('[data-flow-node-summary="true"]')).toBeVisible();

        await card.dblclick({ position: { x: 80, y: 80 }, force: true });
        await expect(card.locator('[data-flow-node-inline-editor="true"]')).toBeVisible();

        await expandButton.click({ force: true });
        await expect(card.locator('[data-flow-node-inline-editor="true"]')).toHaveCount(0);

        await expandButton.click({ force: true });
        await expect(card.locator('[data-flow-node-inline-editor="true"]')).toBeVisible();

        const beforeDrag = await targetNode.boundingBox();
        expect(beforeDrag).not.toBeNull();

        const editorDragOffsetY = Math.min(Math.max((beforeDrag.height * 0.42), 220), beforeDrag.height - 36);
        await dragNodeFromOffset(page, card, 18, editorDragOffsetY, 120, 72);
        await page.waitForTimeout(250);

        const afterFirstDrag = await targetNode.boundingBox();
        expect(afterFirstDrag).not.toBeNull();

        const firstDragDistance = Math.hypot(
            afterFirstDrag.x - beforeDrag.x,
            afterFirstDrag.y - beforeDrag.y,
        );

        expect(firstDragDistance).toBeGreaterThan(24);

        await expandButton.click({ force: true });
        await expect(card.locator('[data-flow-node-inline-editor="true"]')).toHaveCount(0);

        const beforeSecondDrag = await targetNode.boundingBox();
        expect(beforeSecondDrag).not.toBeNull();

        await dragNodeBy(page, dragSurface, -84, 56);
        await page.waitForTimeout(250);

        const afterSecondDrag = await targetNode.boundingBox();
        expect(afterSecondDrag).not.toBeNull();

        const secondDragDistance = Math.hypot(
            afterSecondDrag.x - beforeSecondDrag.x,
            afterSecondDrag.y - beforeSecondDrag.y,
        );

        expect(secondDragDistance).toBeGreaterThan(24);
    });
});
