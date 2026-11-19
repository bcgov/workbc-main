<?php

namespace Drupal\Tests\workbc_ssot\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileInterface;
use Drupal\file\Entity\File;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Drupal\user\Entity\User;
use Drupal\workbc_ssot\Plugin\QueueWorker\SsotUploader;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;

#[Group('workbc_ssot')]
class SsotUploaderTest extends UnitTestCase {

  private string $workbookPath;

  protected function setUp(): void {
    parent::setUp();
    $this->workbookPath = tempnam(sys_get_temp_dir(), 'ssot-upload-');
    file_put_contents($this->workbookPath, 'workbook bytes');
    $file = $this->createMock(FileInterface::class);
    $file->method('getFilename')->willReturn('new, workbook.xlsx');
    $file->method('getFileUri')->willReturn('public://new, workbook.xlsx');
    $user = $this->createMock(UserInterface::class);
    $user->method('getDisplayName')->willReturn('Fixture');
    $file_storage = $this->createMock(EntityStorageInterface::class);
    $file_storage->method('load')->with(1)->willReturn($file);
    $user_storage = $this->createMock(EntityStorageInterface::class);
    $user_storage->method('load')->with(2)->willReturn($user);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturnMap([['file', $file_storage], ['user', $user_storage]]);
    $repository = $this->createMock(EntityTypeRepositoryInterface::class);
    $repository->method('getEntityTypeFromClass')->willReturnMap([[File::class, 'file'], [User::class, 'user']]);
    $filesystem = $this->createMock(FileSystemInterface::class);
    $filesystem->method('realpath')->willReturn($this->workbookPath);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $manager);
    $container->set('entity_type.repository', $repository);
    $container->set('file_system', $filesystem);
    $container->set('logger.factory', $logger_factory);
    $container->set('config.factory', $this->getConfigFactoryStub([
      'workbc' => ['ssot_repo' => [
        'name' => 'example/ssot', 'branch' => 'main', 'path' => 'migration/data',
        'token' => 'fixture', 'committer' => 'Fixture',
      ]],
      'system.site' => ['mail' => 'fixture@example.com'],
    ]));
    \Drupal::setContainer($container);
  }

  protected function tearDown(): void {
    unlink($this->workbookPath);
    parent::tearDown();
  }

  private function upload(): array {
    return [
      'file_id' => 1, 'uid' => 2, 'year' => 2026, 'month' => 1,
      'date' => '2026/08/05 08:00', 'notes' => 'Update',
      'label' => 'Office, "Survey"', 'author' => 'Office',
      'datasets' => [['endpoint' => 'industry_outlook', 'sheet' => 'Sheet1', 'range' => 'A3:H20', 'match' => 'endpoint']],
    ];
  }

  public function testUploadForwardsMetadataWithoutEditingTheManifest(): void {
    $uploader = new TestSsotUploader([], 'ssot_uploader', []);
    $uploader->processItem($this->upload());
    $this->assertCount(3, $uploader->requests);
    $this->assertSame(['GET', 'PUT', 'POST'], array_column($uploader->requests, 'method'));
    $this->assertStringEndsWith('/contents/migration/data/new%2C%20workbook.xlsx?ref=main', $uploader->requests[0]['endpoint']);
    $this->assertSame('existing-sha', $uploader->requests[1]['body']['sha']);
    $this->assertSame(base64_encode('workbook bytes'), $uploader->requests[1]['body']['content']);
    $dispatch = $uploader->requests[2]['body'];
    $this->assertSame('ssot_update', $dispatch['event_type']);
    $this->assertSame('new, workbook.xlsx', $dispatch['client_payload']['filename']);
    $this->assertSame($uploader->uploadedSha, $dispatch['client_payload']['workbook_sha']);
    $this->assertSame([
      'label' => 'Office, "Survey"', 'author' => 'Office', 'datasets' => $this->upload()['datasets'],
    ], $dispatch['client_payload']['metadata']);
    $this->assertSame('2026/08/05 08:00', $dispatch['client_payload']['date']);
  }

  public function testMissingWorkbookIsCreated(): void {
    $uploader = new TestSsotUploader([], 'ssot_uploader', []);
    $uploader->lookupStatus = 404;
    $uploader->processItem($this->upload());
    $this->assertNull($uploader->requests[1]['body']['sha']);
    $this->assertCount(3, $uploader->requests);
  }

  public function testLookupErrorsDoNotBecomeFileCreation(): void {
    $uploader = new TestSsotUploader([], 'ssot_uploader', []);
    $uploader->lookupStatus = 403;
    $this->expectException(ClientException::class);
    try {
      $uploader->processItem($this->upload());
    }
    finally {
      $this->assertCount(1, $uploader->requests);
    }
  }

  public function testUploadFailureDoesNotDispatch(): void {
    $uploader = new TestSsotUploader([], 'ssot_uploader', []);
    $uploader->failUpload = TRUE;
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Upload failed');
    try {
      $uploader->processItem($this->upload());
    }
    finally {
      $this->assertCount(2, $uploader->requests);
    }
  }

  public function testMissingUploadIdentityDoesNotDispatch(): void {
    $uploader = new TestSsotUploader([], 'ssot_uploader', []);
    $uploader->uploadedSha = 'invalid';
    $this->expectException(\UnexpectedValueException::class);
    try {
      $uploader->processItem($this->upload());
    }
    finally {
      $this->assertCount(2, $uploader->requests);
    }
  }

}

class TestSsotUploader extends SsotUploader {

  public array $requests = [];
  public int $lookupStatus = 200;
  public bool $failUpload = FALSE;
  public string $uploadedSha = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

  protected function github($endpoint, $method, $token, $body = NULL) {
    $this->requests[] = ['endpoint' => $endpoint, 'method' => $method, 'body' => $body];
    if ($method === 'GET') {
      if ($this->lookupStatus !== 200) {
        throw new ClientException('Lookup failed', new Request('GET', $endpoint), new Response($this->lookupStatus));
      }
      return new Response(200, [], '{"sha":"existing-sha"}');
    }
    if ($method === 'PUT') {
      if ($this->failUpload) {
        throw new \RuntimeException('Upload failed');
      }
      return new Response(200, [], json_encode(['content' => ['sha' => $this->uploadedSha]], JSON_THROW_ON_ERROR));
    }
    return new Response(204);
  }

}
