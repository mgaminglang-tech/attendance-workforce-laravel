<?php

namespace Tests\Feature;

use App\Mail\EmployeeInvitationMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmployeeInvitationMailTest extends TestCase
{
    public function test_invitation_renders_workforce_html_and_plain_text_with_the_same_setup_link(): void
    {
        $this->travelTo('2031-01-01 09:00:00');
        config(['app.name' => 'Laravel']);
        $user = User::factory()->employee()->pending()->make(['name' => 'Ada & José']);
        $token = str_repeat('a', 64);
        $activationUrl = route('invitations.show', ['token' => $token]);

        $mail = new EmployeeInvitationMail($user, $token);

        $mail->assertHasSubject('Set up your workforce account');
        $mail->assertSeeInHtml('Workforce');
        $mail->assertSeeInHtml('Attendance & Workforce Management');
        $mail->assertSeeInHtml('Welcome, Ada & José');
        $mail->assertSeeInHtml('An administrator created your Employee Timekeeping & Workforce Management System account.');
        $mail->assertSeeInHtml('Use the secure link below to choose your password.');
        $mail->assertSeeInHtml('This invitation link expires and can only be used once.');
        $mail->assertSeeInHtml('Set up my account');
        $mail->assertSeeInHtml($activationUrl);
        $mail->assertSeeInHtml('If you were not expecting this invitation, contact your administrator.');
        $mail->assertSeeInHtml('2031 Workforce. All rights reserved.');
        $mail->assertDontSeeInHtml('Laravel');
        $mail->assertDontSeeInHtml('laravel.com');

        $mail->assertSeeInText('Workforce');
        $mail->assertSeeInText('Attendance & Workforce Management');
        $mail->assertSeeInText('Welcome, Ada & José');
        $mail->assertSeeInText('An administrator created your Employee Timekeeping & Workforce Management System account.');
        $mail->assertSeeInText('Use the secure link below to choose your password.');
        $mail->assertSeeInText('Set up your account:');
        $mail->assertSeeInText($activationUrl);
        $mail->assertSeeInText('This invitation link expires and can only be used once.');
        $mail->assertSeeInText('If you were not expecting this invitation, contact your administrator.');
        $mail->assertSeeInText('2031 Workforce. All rights reserved.');
        $mail->assertDontSeeInText('Laravel');
    }

    public function test_invitation_escapes_employee_name_in_html(): void
    {
        $user = User::factory()->employee()->pending()->make([
            'name' => 'Ada <script>alert("unsafe")</script>',
        ]);

        $mail = new EmployeeInvitationMail($user, str_repeat('b', 64));

        $mail->assertSeeInHtml($user->name);
        $mail->assertDontSeeInHtml('<script>', escape: false);
    }

    public function test_configured_mailer_sends_both_bodies_with_the_configured_sender_and_recipient(): void
    {
        config([
            'mail.default' => 'array',
            'mail.from.address' => 'hr@example.test',
            'mail.from.name' => 'Example HR',
        ]);
        $user = User::factory()->employee()->pending()->make(['email' => 'employee@example.test']);
        $token = str_repeat('c', 64);
        $activationUrl = route('invitations.show', ['token' => $token]);

        $sent = Mail::to($user)->send(new EmployeeInvitationMail($user, $token));

        $message = $sent->getSymfonySentMessage()->getOriginalMessage();
        $this->assertSame('hr@example.test', $message->getFrom()[0]->getAddress());
        $this->assertSame('Example HR', $message->getFrom()[0]->getName());
        $this->assertSame('employee@example.test', $message->getTo()[0]->getAddress());
        $this->assertSame('Set up your workforce account', $message->getSubject());
        $this->assertStringContainsString($activationUrl, $message->getHtmlBody());
        $this->assertStringContainsString($activationUrl, $message->getTextBody());
        $this->assertSame([], $message->getAttachments());
    }
}
