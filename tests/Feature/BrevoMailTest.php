<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

it('sends membership email through the configured Brevo HTTPS transport', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-accepted'], 201)]);
    config(['mail.mailers.brevo.key' => 'test-only-key']);
    Mail::purge('brevo');
    Mail::mailer('brevo')->raw('Your membership reference is TXN-TEST', fn ($message) => $message->to('qa@example.test')->subject('Membership received'));
    Http::assertSent(fn ($request) => $request->hasHeader('api-key', 'test-only-key') && $request['to'][0]['email'] === 'qa@example.test' && $request['textContent'] === 'Your membership reference is TXN-TEST');
});
it('reports provider failures without disclosing the provider response', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.brevo.com/v3/smtp/email' => Http::response(['message' => 'secret-sensitive-provider-detail'], 400)]);
    config(['mail.mailers.brevo.key' => 'test-only-key']);
    Mail::purge('brevo');
    expect(fn () => Mail::mailer('brevo')->raw('Test', fn ($message) => $message->to('qa@example.test')->subject('Test')))->toThrow(TransportException::class, 'The email provider did not accept the message.');
    Http::assertSentCount(1);
});
it('does not contact the provider when the key is missing', function () {
    Http::preventStrayRequests();
    Http::fake();
    config(['mail.mailers.brevo.key' => '']);
    Mail::purge('brevo');
    expect(fn () => Mail::mailer('brevo')->raw('Test', fn ($message) => $message->to('qa@example.test')->subject('Test')))->toThrow(TransportException::class, 'Configure the Brevo API key');
    Http::assertNothingSent();
});
