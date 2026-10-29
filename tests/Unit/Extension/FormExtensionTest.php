<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Test\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xoops\SmartyExtensions\Extension\FormExtension;
use Xoops\SmartyExtensions\Test\Stubs\TemplateStub;

#[CoversClass(FormExtension::class)]
final class FormExtensionTest extends TestCase
{
    private function tpl(): object
    {
        return $this->createMock(TemplateStub::class);
    }

    // ──────────────────────────────────────────────
    // Registry
    // ──────────────────────────────────────────────

    #[Test]
    public function getFunctionsReturnsEightEntries(): void
    {
        $ext = new FormExtension();
        $functions = $ext->getFunctions();
        $this->assertCount(8, $functions);
        $this->assertArrayHasKey('form_open', $functions);
        $this->assertArrayHasKey('form_close', $functions);
        $this->assertArrayHasKey('form_input', $functions);
        $this->assertArrayHasKey('create_button', $functions);
        $this->assertArrayHasKey('render_form_errors', $functions);
        $this->assertArrayHasKey('validate_form', $functions);
        $this->assertArrayHasKey('validate_email', $functions);
        $this->assertArrayHasKey('display_error', $functions);
    }

    // ──────────────────────────────────────────────
    // form_open
    // ──────────────────────────────────────────────

    #[Test]
    public function formOpenRendersFormTag(): void
    {
        $ext = new FormExtension();
        $result = $ext->formOpen(['action' => 'save.php', 'method' => 'post'], $this->tpl());
        $this->assertStringContainsString('<form', $result);
        $this->assertStringContainsString('action="save.php"', $result);
        $this->assertStringContainsString('method="post"', $result);
    }

    #[Test]
    public function formOpenInjectsCsrfTokenForPostWhenSecurityAvailable(): void
    {
        $ext = new FormExtension(new \XoopsSecurity());
        $result = $ext->formOpen(['action' => 'save.php', 'method' => 'post'], $this->tpl());
        $this->assertStringContainsString('token', $result);
        $this->assertStringContainsString('input', $result);
    }

    #[Test]
    public function formOpenDoesNotInjectCsrfTokenForGetMethod(): void
    {
        $ext = new FormExtension(new \XoopsSecurity());
        $result = $ext->formOpen(['action' => 'search.php', 'method' => 'get'], $this->tpl());
        // A GET form must NOT inject a CSRF token — it would appear in the URL
        $this->assertStringNotContainsString('token', $result);
    }

    #[Test]
    public function formOpenDoesNotInjectCsrfWhenNoSecurity(): void
    {
        $ext = new FormExtension(null);
        $result = $ext->formOpen(['action' => 'save.php', 'method' => 'post'], $this->tpl());
        $this->assertStringNotContainsString('token', $result);
    }

    #[Test]
    public function formOpenPassesThroughEnctype(): void
    {
        $ext = new FormExtension();
        $result = $ext->formOpen(
            ['action' => 'upload.php', 'method' => 'post', 'enctype' => 'multipart/form-data'],
            $this->tpl(),
        );
        $this->assertStringContainsString('enctype="multipart/form-data"', $result);
    }

    #[Test]
    public function formOpenEscapesActionAttribute(): void
    {
        $ext = new FormExtension();
        $result = $ext->formOpen(['action' => 'save.php?a=1&b=2', 'method' => 'post'], $this->tpl());
        $this->assertStringContainsString('save.php?a=1&amp;b=2', $result);
    }

    // ──────────────────────────────────────────────
    // form_close
    // ──────────────────────────────────────────────

    #[Test]
    public function formCloseReturnsClosingTag(): void
    {
        $ext = new FormExtension();
        $this->assertSame('</form>', $ext->formClose([], $this->tpl()));
    }

    // ──────────────────────────────────────────────
    // form_input
    // ──────────────────────────────────────────────

    #[Test]
    public function formInputRendersInputTag(): void
    {
        $ext = new FormExtension();
        $result = $ext->formInput(['type' => 'text', 'name' => 'title', 'value' => 'Hello'], $this->tpl());
        $this->assertStringContainsString('<input', $result);
        $this->assertStringContainsString('type="text"', $result);
        $this->assertStringContainsString('name="title"', $result);
        $this->assertStringContainsString('value="Hello"', $result);
    }

    #[Test]
    public function formInputEscapesValueForXss(): void
    {
        $ext = new FormExtension();
        $result = $ext->formInput(['name' => 'q', 'value' => '<script>alert(1)</script>'], $this->tpl());
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    #[Test]
    public function formInputPassesThroughExtraAttributes(): void
    {
        $ext = new FormExtension();
        $result = $ext->formInput(
            ['name' => 'email', 'type' => 'email', 'class' => 'form-control', 'placeholder' => 'you@example.com'],
            $this->tpl(),
        );
        $this->assertStringContainsString('class="form-control"', $result);
        $this->assertStringContainsString('placeholder="you@example.com"', $result);
    }

    // ──────────────────────────────────────────────
    // create_button
    // ──────────────────────────────────────────────

    #[Test]
    public function createButtonRendersButton(): void
    {
        $ext = new FormExtension();
        $result = $ext->createButton(['label' => 'Save', 'type' => 'submit'], $this->tpl());
        $this->assertStringContainsString('<button', $result);
        $this->assertStringContainsString('type="submit"', $result);
        $this->assertStringContainsString('Save', $result);
    }

    #[Test]
    public function createButtonWithIcon(): void
    {
        $ext = new FormExtension();
        $result = $ext->createButton(['label' => 'Delete', 'icon' => 'bi-trash'], $this->tpl());
        $this->assertStringContainsString('class="bi-trash"', $result);
        $this->assertStringContainsString('Delete', $result);
    }

    #[Test]
    public function createButtonAssignsHtmlToVariable(): void
    {
        $ext = new FormExtension();
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('btn', $this->stringContains('<button'));

        $result = $ext->createButton(['label' => 'Save', 'assign' => 'btn'], $tpl);
        $this->assertSame('', $result);
    }

    // ──────────────────────────────────────────────
    // render_form_errors
    // ──────────────────────────────────────────────

    #[Test]
    public function renderFormErrorsReturnsEmptyWhenNoErrors(): void
    {
        $ext = new FormExtension();
        $this->assertSame('', $ext->renderFormErrors(['errors' => []], $this->tpl()));
    }

    #[Test]
    public function renderFormErrorsRendersErrorList(): void
    {
        $ext = new FormExtension();
        $result = $ext->renderFormErrors(
            ['errors' => ['email' => 'Invalid email address']],
            $this->tpl(),
        );
        $this->assertStringContainsString('alert alert-danger', $result);
        $this->assertStringContainsString('email', $result);
        $this->assertStringContainsString('Invalid email address', $result);
    }

    #[Test]
    public function renderFormErrorsHandlesArrayOfErrors(): void
    {
        $ext = new FormExtension();
        $result = $ext->renderFormErrors(
            ['errors' => ['name' => ['This field is required', 'Minimum length is 3 characters']]],
            $this->tpl(),
        );
        $this->assertStringContainsString('This field is required', $result);
        $this->assertStringContainsString('Minimum length is 3 characters', $result);
    }

    #[Test]
    public function renderFormErrorsAssignsHtml(): void
    {
        $ext = new FormExtension();
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('errs', $this->stringContains('alert'));

        $result = $ext->renderFormErrors(
            ['errors' => ['title' => 'Required'], 'assign' => 'errs'],
            $tpl,
        );
        $this->assertSame('', $result);
    }

    // ──────────────────────────────────────────────
    // validate_form
    // ──────────────────────────────────────────────

    #[Test]
    public function validateFormRequiredRuleCatchesEmptyField(): void
    {
        $ext = new FormExtension();
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('errors', $this->callback(static fn($v) => isset($v['title'])));

        $ext->validateForm(
            ['data' => ['title' => ''], 'rules' => ['title' => ['required' => true]], 'assign' => 'errors'],
            $tpl,
        );
    }

    #[Test]
    public function validateFormMinLengthRuleWithMultibyteChars(): void
    {
        // Each Japanese character is 3 bytes but 1 char — mb_strlen must be used
        $ext = new FormExtension();
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('errors', $this->callback(static fn($v) => empty($v)));

        // 'あいうえお' = 5 multibyte chars; min_length is 5 → should PASS
        $ext->validateForm(
            [
                'data' => ['username' => 'あいうえお'],
                'rules' => ['username' => ['min_length' => 5]],
                'assign' => 'errors',
            ],
            $tpl,
        );
    }

    #[Test]
    public function validateFormMaxLengthRuleWithMultibyteChars(): void
    {
        // 'あいうえお' = 5 chars; max_length is 3 → should FAIL
        $ext = new FormExtension();
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('errors', $this->callback(static fn($v) => isset($v['bio'])));

        $ext->validateForm(
            [
                'data' => ['bio' => 'あいうえお'],
                'rules' => ['bio' => ['max_length' => 3]],
                'assign' => 'errors',
            ],
            $tpl,
        );
    }

    #[Test]
    public function validateFormEmailRuleCatchesInvalidAddress(): void
    {
        $ext = new FormExtension();
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('errors', $this->callback(static fn($v) => isset($v['email'])));

        $ext->validateForm(
            ['data' => ['email' => 'not-an-email'], 'rules' => ['email' => ['email' => true]], 'assign' => 'errors'],
            $tpl,
        );
    }

    #[Test]
    public function validateFormNumericRuleCatchesNonNumeric(): void
    {
        $ext = new FormExtension();
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('errors', $this->callback(static fn($v) => isset($v['age'])));

        $ext->validateForm(
            ['data' => ['age' => 'abc'], 'rules' => ['age' => ['numeric' => true]], 'assign' => 'errors'],
            $tpl,
        );
    }

    // ──────────────────────────────────────────────
    // validate_email
    // ──────────────────────────────────────────────

    #[Test]
    public function validateEmailReturnsOneForValidAddress(): void
    {
        $ext = new FormExtension();
        $result = $ext->validateEmail(['email' => 'user@example.com'], $this->tpl());
        $this->assertSame('1', $result);
    }

    #[Test]
    public function validateEmailReturnsEmptyForInvalidAddress(): void
    {
        $ext = new FormExtension();
        $result = $ext->validateEmail(['email' => 'not-valid'], $this->tpl());
        $this->assertSame('', $result);
    }

    #[Test]
    public function validateEmailAssignsBoolWhenAssignSet(): void
    {
        $ext = new FormExtension();
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('isValid', true);

        $result = $ext->validateEmail(['email' => 'ok@example.com', 'assign' => 'isValid'], $tpl);
        $this->assertSame('', $result);
    }

    // ──────────────────────────────────────────────
    // display_error
    // ──────────────────────────────────────────────

    #[Test]
    public function displayErrorRendersAlertDiv(): void
    {
        $ext = new FormExtension();
        $result = $ext->displayError(['message' => 'Something went wrong'], $this->tpl());
        $this->assertStringContainsString('alert alert-danger', $result);
        $this->assertStringContainsString('Something went wrong', $result);
    }

    #[Test]
    public function displayErrorReturnsEmptyForEmptyMessage(): void
    {
        $ext = new FormExtension();
        $this->assertSame('', $ext->displayError(['message' => ''], $this->tpl()));
    }

    #[Test]
    public function displayErrorEscapesMessage(): void
    {
        $ext = new FormExtension();
        $result = $ext->displayError(['message' => '<b>Danger!</b>'], $this->tpl());
        $this->assertStringNotContainsString('<b>', $result);
        $this->assertStringContainsString('&lt;b&gt;', $result);
    }

    #[Test]
    public function displayErrorAssignsHtml(): void
    {
        $ext = new FormExtension();
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('err', $this->stringContains('alert'));

        $result = $ext->displayError(['message' => 'Oops', 'assign' => 'err'], $tpl);
        $this->assertSame('', $result);
    }

    // ──────────────────────────────────────────────
    // Hardening regressions
    // ──────────────────────────────────────────────

    #[Test]
    public function formOpenDropsExecutableAction(): void
    {
        $ext = new FormExtension(new \XoopsSecurity());
        $result = $ext->formOpen(['action' => 'javascript:alert(1)', 'method' => 'post'], $this->tpl());
        $this->assertStringContainsString('action=""', $result);
        $this->assertStringNotContainsString('javascript', $result);
    }

    #[Test]
    public function formOpenOmitsTokenForExternalActions(): void
    {
        $ext = new FormExtension(new \XoopsSecurity());
        foreach (['https://evil.example/steal', '//evil.example/a', '/\\evil.example/a', ' //evil.example/a', "/\t/evil.example/a"] as $action) {
            $result = $ext->formOpen(['action' => $action, 'method' => 'post'], $this->tpl());
            $this->assertStringNotContainsString('test-token', $result, $action);
        }
    }

    #[Test]
    public function formOpenKeepsTokenForRelativeActions(): void
    {
        $ext = new FormExtension(new \XoopsSecurity());
        foreach (['', 'save.php', '/modules/x/save.php', '?op=save'] as $action) {
            $result = $ext->formOpen(['action' => $action, 'method' => 'post'], $this->tpl());
            $this->assertStringContainsString('test-token', $result, $action);
        }
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function formOpenKeepsTokenOnlyForSiteHostWhenAbsolute(): void
    {
        \define('XOOPS_URL', 'https://example.com');
        $ext = new FormExtension(new \XoopsSecurity());

        $own = $ext->formOpen(['action' => 'https://EXAMPLE.com/save.php', 'method' => 'post'], $this->tpl());
        $this->assertStringContainsString('test-token', $own);

        $this->assertStringContainsString('test-token', $ext->formOpen(['action' => '//example.com/a', 'method' => 'post'], $this->tpl()));
        $this->assertStringContainsString('test-token', $ext->formOpen(['action' => 'https://example.com:443/a', 'method' => 'post'], $this->tpl()));

        $external = [
            'https://example.com.evil.net/a',
            'https://evil.net/example.com',
            '//evil.net/a',
            'http://example.com/steal',      // scheme differs
            'https://example.com:8080/steal', // port differs
        ];
        foreach ($external as $action) {
            $result = $ext->formOpen(['action' => $action, 'method' => 'post'], $this->tpl());
            $this->assertStringNotContainsString('test-token', $result, $action);
        }
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function formOpenTreatsEquivalentIpv6HostsAsSameOrigin(): void
    {
        \define('XOOPS_URL', 'https://[::1]');
        $ext = new FormExtension(new \XoopsSecurity());
        $result = $ext->formOpen(['action' => 'https://[0:0:0:0:0:0:0:1]/save', 'method' => 'post'], $this->tpl());
        $this->assertStringContainsString('test-token', $result);

        // Malformed literals and zone ids are never same-origin
        foreach (['https://[[::1]]/save', 'https://[::1%25eth0]/save', 'https://[::1%eth0]/save'] as $action) {
            $this->assertStringNotContainsString('test-token', $ext->formOpen(['action' => $action, 'method' => 'post'], $this->tpl()), $action);
        }
    }

    #[Test]
    public function formInputDropsExternalFormaction(): void
    {
        // formaction overrides a token-carrying form's action, so it must stay on-site
        $ext = new FormExtension();
        $external = $ext->formInput(['type' => 'submit', 'name' => 'go', 'formaction' => 'https://evil.example/steal'], $this->tpl());
        $this->assertStringNotContainsString('formaction', $external);

        $local = $ext->formInput(['type' => 'submit', 'name' => 'go', 'formaction' => '/modules/x/save.php'], $this->tpl());
        $this->assertStringContainsString('formaction="/modules/x/save.php"', $local);
    }

    #[Test]
    public function formInputDropsUnsafeAttributeNames(): void
    {
        $ext = new FormExtension();
        $result = $ext->formInput([
            'name'             => 'q',
            'onfocus'          => 'alert(1)',
            'autofocus onfocus' => 'alert(1)',
            "x\n"              => 'y',
            'formaction'       => 'javascript:alert(1)',
            'data-id'          => '7',
            'aria-label'       => 'Search',
            'placeholder'      => 'Find',
        ], $this->tpl());

        $this->assertStringNotContainsString('onfocus', $result);
        $this->assertStringNotContainsString('javascript', $result);
        $this->assertStringNotContainsString("x\n", $result);
        $this->assertStringContainsString('data-id="7"', $result);
        $this->assertStringContainsString('aria-label="Search"', $result);
        $this->assertStringContainsString('placeholder="Find"', $result);
    }
}
