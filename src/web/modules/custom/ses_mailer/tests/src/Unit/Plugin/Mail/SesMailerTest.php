<?php

namespace Drupal\Tests\ses_mailer\Unit\Plugin\Mail;

use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\Ses\SesClient;
use Drupal\ses_mailer\Plugin\Mail\SesMailer;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * SesMailerTest Cases.
 *
 * @group ses_mailer
 * @coversDefaultClass \Drupal\ses_mailer\Plugin\Mail\SesMailer
 */
class SesMailerTest extends UnitTestCase {

  /**
   * The SES mailer under test.
   *
   * @var \Drupal\ses_mailer\Plugin\Mail\SesMailer
   */
  protected $mailer;

  /**
   * The logger.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject|\Psr\Log\LoggerInterface
   */
  protected $logger;


  /**
   * The SES Client.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject|\Aws\Ses\SesClient
   */
  protected $client;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->client = $this->getMockBuilder(SesClient::class)
      ->disableOriginalConstructor()
      ->setMethods(['sendEmail'])
      ->getMock();

    $this->logger = $this->createMock(LoggerInterface::class);

    $this->mailer = new SesMailer([], 'ses_mail', [], $this->client, $this->logger);
  }

  /**
   * Tests the mail function.
   *
   * @covers ::mail
   */
  public function testMailSuccess() {

    $result = new Result(['MessageId' => 'abc123']);
    $this->client->expects($this->once())
      ->method('sendEmail')
      ->withAnyParameters()
      ->willReturn($result);

    $this->logger->expects($this->once())
      ->method('info');

    $message = [
      'to' => 'recipient@example.com',
      'body' => 'Lorem ipsum...',
      'subject' => 'Test email',
      'from' => 'sender@example.com',
      'reply-to' => 'reply@example.com',
    ];

    $result = $this->mailer->mail($message);

    $this->assertFalse($result['error']);

  }

  /**
   * Tests a mail error.
   *
   * @covers ::mail
   */
  public function testMailError() {

    /* @var \PHPUnit_Framework_MockObject_MockObject|\Aws\CommandInterface $command */
    $command = $this->createMock(CommandInterface::class);
    $context = [
      'type' => 'test type',
      'code' => '123',
    ];
    $exception = new AwsException('test message', $command, $context);
    $this->client->method('sendEmail')
      ->will($this->throwException($exception));

    $this->logger->expects($this->once())
      ->method('error');

    $message = [
      'to' => 'recipient@example.com',
      'body' => 'Lorem ipsum...',
      'subject' => 'Test email',
      'from' => 'sender@example.com',
      'reply-to' => 'reply@example.com',
    ];

    $result = $this->mailer->mail($message);

    $this->assertEquals($result['message'], 'test type');
    $this->assertEquals($result['errorCode'], '123');
    $this->assertTrue($result['error']);
  }

}
