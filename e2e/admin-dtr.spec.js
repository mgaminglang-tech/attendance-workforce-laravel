import { test, expect } from '@playwright/test';

test.beforeEach(async ({ page, isMobile }) => {
    await page.goto('/admin/dashboard');

    if (isMobile) {
        await page.getByRole('button', { name: 'Open navigation', exact: true }).tap();
    }

    const navigation = page.getByRole('navigation', {
        name: isMobile ? 'Mobile primary navigation' : 'Desktop primary navigation',
        exact: true,
    });

    await navigation.getByRole('link', { name: 'DTR', exact: true }).click();
    await expect(page).toHaveURL((url) => url.pathname === '/admin/dtr');
    await expect(page.getByRole('heading', { name: 'Employee Monthly DTR', exact: true })).toBeVisible();
    await expect(page.getByRole('combobox', { name: 'Employee', exact: true })).toBeVisible();
    await expect(page.getByLabel('Month', { exact: true })).toHaveAttribute('type', 'month');
});

async function changeMonth(input, control) {
    const initialMonth = await input.inputValue();
    const month = initialMonth === '2026-09' ? '2026-08' : '2026-09';
    const label = month === '2026-09' ? 'September 2026' : 'August 2026';

    await input.fill(month);
    await input.press('Tab');
    await expect(input).toHaveValue(month);
    await expect(control.getByText(label, { exact: true })).toBeVisible();

    return { month, label };
}

test('Month control focuses its native input and reflects a changed month @month-control', async ({ page, isMobile }) => {
    const input = page.getByLabel('Month', { exact: true });
    // The styled control has no separate accessible role; keep this locator scoped to its component.
    const control = page.locator('[data-month-picker] .month-picker-control');

    await expect(control).toBeVisible();
    await expect(input).toBeEnabled();
    await input.scrollIntoViewIfNeeded();

    const hitArea = await input.evaluate((element) => {
        const control = element.closest('.month-picker-control');
        const inputBounds = element.getBoundingClientRect();
        const controlBounds = control.getBoundingClientRect();
        const points = [0.1, 0.5, 0.9].flatMap((x) => [0.2, 0.5, 0.8].map((y) => ({
            x: controlBounds.left + controlBounds.width * x,
            y: controlBounds.top + controlBounds.height * y,
        })));

        return {
            inputWidth: inputBounds.width,
            inputHeight: inputBounds.height,
            controlWidth: controlBounds.width,
            controlHeight: controlBounds.height,
            hitsInput: points.map(({ x, y }) => document.elementFromPoint(x, y) === element),
        };
    });

    expect(hitArea.controlWidth).toBeGreaterThan(44);
    expect(hitArea.controlHeight).toBeGreaterThanOrEqual(44);
    expect(hitArea.inputWidth).toBeGreaterThan(44);
    expect(hitArea.inputHeight).toBeGreaterThanOrEqual(44);
    expect(hitArea.hitsInput).toEqual(Array(9).fill(true));

    if (isMobile) {
        await control.tap();
    } else {
        await control.click();
    }

    await expect(input).toBeFocused();
    // Native picker UI is outside the DOM; dismiss it without inspecting its contents.
    await page.keyboard.press('Escape');
    await changeMonth(input, control);
});

test('Preview DTR preserves the selected employee and month', async ({ page }) => {
    const employeeSelector = page.getByRole('combobox', { name: 'Employee', exact: true });
    const input = page.getByLabel('Month', { exact: true });
    const control = page.locator('[data-month-picker] .month-picker-control');
    const { month, label } = await changeMonth(input, control);

    await employeeSelector.click();
    const options = page.getByRole('listbox', { name: 'Employee', exact: true })
        .getByRole('option', { disabled: false });

    test.skip(await options.count() === 0, 'No employee test data is available for a DTR preview.');

    const employeeOption = options.first();
    const employeeName = await employeeOption.locator('.employee-picker-option-name').innerText();

    await employeeOption.click();
    const employeeId = await page.locator('select[name="employee_id"]').inputValue();
    expect(employeeId).not.toBe('');

    const responsePromise = page.waitForResponse((response) => {
        const url = new URL(response.url());

        return response.request().isNavigationRequest()
            && url.pathname === '/admin/dtr/preview';
    });

    await page.getByRole('button', { name: 'Preview DTR', exact: true }).click();
    const response = await responsePromise;
    const requestUrl = new URL(response.request().url());

    expect(response.status()).toBe(200);
    expect(requestUrl.searchParams.get('month')).toBe(month);
    expect(requestUrl.searchParams.get('employee_id')).toBe(employeeId);
    await expect(page).toHaveURL((url) => url.pathname === '/admin/dtr/preview'
        && url.searchParams.get('month') === month
        && url.searchParams.get('employee_id') === employeeId);
    await expect(input).toHaveValue(month);
    await expect(control.getByText(label, { exact: true })).toBeVisible();
    await expect.poll(() => employeeSelector.inputValue()).toContain(employeeName);

    const preview = page.getByRole('region', { name: employeeName, exact: true });

    await expect(preview.getByText('Monthly DTR preview', { exact: true })).toBeVisible();
    await expect(preview.getByText(label)).toBeVisible();
    await expect(preview.getByRole('region', { name: 'Monthly DTR table', exact: true })).toBeVisible();
    await expect(preview.getByRole('link', { name: 'Download PDF', exact: true })).toBeVisible();
});
