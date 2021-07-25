<?php

namespace Symfony\Component\Mailer\Bridge\Mailgun\Transport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\AbstractStream;
use Symfony\Component\Mailer\Transport\Smtp\Stream\ProcessStream;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MailhogSendmailTransport extends AbstractTransport {

  private $command = "/usr/local/bin/mhsendmail --smtp-addr='sendmailhog:1025'";

  private $stream;

  private $transport;

  /**
   * Constructor.
   *
   * If using -t mode you are strongly advised to include -oi or -i in the
   * flags. For example: /usr/sbin/sendmail -oi -t
   * -f<sender> flag will be appended automatically if one is not present.
   *
   * The recommended mode is "-bs" since it is interactive and failure
   * notifications are hence possible.
   */
  public function __construct(string $command = NULL, EventDispatcherInterface $dispatcher = NULL, LoggerInterface $logger = NULL) {
    parent::__construct($dispatcher, $logger);

    if (NULL !== $command) {
      $this->command = $command;
    }

    $this->stream = new ProcessStream();
  }

  public function send(RawMessage $message, Envelope $envelope = NULL): ?SentMessage {
    if ($this->transport) {
      return $this->transport->send($message, $envelope);
    }

    return parent::send($message, $envelope);
  }

  public function __toString(): string {
    if ($this->transport) {
      return (string) $this->transport;
    }

    return 'smtp://sendmail';
  }

  protected function doSend(SentMessage $message): void {
    $this->getLogger()
      ->debug(sprintf('Email transport "%s" starting', __CLASS__));

    $command = $this->command;
    if (FALSE === strpos($command, ' -f')) {
      $command .= ' -f' . escapeshellarg($message->getEnvelope()
          ->getSender()
          ->getEncodedAddress());
    }

    $chunks = AbstractStream::replace("\r\n", "\n", $message->toIterable());

    if (FALSE === strpos($command, ' -i') && FALSE === strpos($command, ' -oi')) {
      $chunks = AbstractStream::replace("\n.", "\n..", $chunks);
    }

    $this->stream->setCommand($command);
    $this->stream->initialize();
    foreach ($chunks as $chunk) {
      $this->stream->write($chunk);
    }
    $this->stream->flush();
    $this->stream->terminate();

    $this->getLogger()
      ->debug(sprintf('Email transport "%s" stopped', __CLASS__));
  }

}
