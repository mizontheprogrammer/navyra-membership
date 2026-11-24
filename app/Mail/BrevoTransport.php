<?php

namespace App\Mail;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class BrevoTransport extends AbstractTransport
{
    public function __construct(private readonly string $key)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if ($this->key === '') {
            throw new TransportException('Configure the Brevo API key before sending email.');
        }
        $email = $message->getOriginalMessage();
        if (! $email instanceof Email) {
            throw new TransportException('Brevo requires a structured email.');
        }
        $address = static fn (Address $a): array => array_filter(['email' => $a->getAddress(), 'name' => $a->getName()], static fn ($v) => $v !== '');
        $payload = ['sender' => $address($email->getFrom()[0]), 'subject' => $email->getSubject()];
        foreach (['to' => $email->getTo(), 'cc' => $email->getCc(), 'bcc' => $email->getBcc()] as $field => $recipients) {
            if ($recipients) {
                $payload[$field] = array_map($address, $recipients);
            }
        }
        if ($email->getReplyTo()) {
            $payload['replyTo'] = $address($email->getReplyTo()[0]);
        }
        if ($email->getHtmlBody() !== null) {
            $payload['htmlContent'] = $email->getHtmlBody();
        } else {
            $payload['textContent'] = $email->getTextBody() ?? '';
        }
        if ($email->getAttachments()) {
            throw new TransportException('This membership mail transport does not support attachments.');
        }
        try {
            $response = Http::acceptJson()->withHeaders(['api-key' => $this->key])->connectTimeout(3)->timeout(15)->post('https://api.brevo.com/v3/smtp/email', $payload);
        } catch (ConnectionException) {
            throw new TransportException('The email provider could not be reached.');
        }
        if (! $response->successful() || ! is_string($response->json('messageId'))) {
            throw new TransportException('The email provider did not accept the message. Check the sender and provider configuration.');
        }
        $message->setMessageId($response->json('messageId'));
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}
