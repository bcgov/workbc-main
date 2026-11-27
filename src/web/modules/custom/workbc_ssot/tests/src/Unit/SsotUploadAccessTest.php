<?php

namespace Drupal\Tests\workbc_ssot\Unit;

use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Access\PermissionAccessCheck;
use Drupal\user\PermissionHandlerInterface;
use Drupal\workbc_ssot\Form\SsotUploadFormBase;
use Drupal\workbc_ssot\Routing\SsotUploadAccess;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests upload permissions, route declarations, and navigation.
 */
#[Group('workbc_ssot')]
class SsotUploadAccessTest extends SsotUploadTestBase {

  protected function setUp(): void {
    parent::setUp();
    $container = \Drupal::getContainer();
    $container->set('cache_contexts_manager', new CacheContextsManager($container, ['user.permissions']));
  }

  private function modulePath(): string {
    return dirname(__DIR__, 3);
  }

  private function account(array $permissions): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(
      static fn(string $permission): bool => in_array($permission, $permissions, TRUE)
    );
    return $account;
  }

  public function testFormPermissionsAndRoutes(): void {
    $path = $this->modulePath();
    $routes = Yaml::parseFile("$path/workbc_ssot.routing.yml");
    $permissions = Yaml::parseFile("$path/workbc_ssot.permissions.yml");
    $links = Yaml::parseFile("$path/workbc_ssot.links.menu.yml");
    $services = Yaml::parseFile("$path/workbc_ssot.services.yml");
    $log = Yaml::parseFile(dirname($path, 4) . '/config/sync/views.view.ssot_log.yml');
    $forms = [];
    foreach (glob("$path/src/Form/SsotUpload*Form.php") as $file) {
      $class = 'Drupal\\workbc_ssot\\Form\\' . basename($file, '.php');
      $form = new $class();
      $this->assertInstanceOf(SsotUploadFormBase::class, $form);
      $reflection = new \ReflectionClass($form);
      $id = $reflection->getProperty('id')->getValue($form);
      $title = $reflection->getProperty('title')->getValue($form);
      $route_name = $reflection->getMethod('getRouteName')->invoke($form);
      $permission = 'upload ssot data sheet ' . $id;

      $this->assertSame("Upload data sheet $title", $permissions[$permission]['title']);
      $this->assertSame('\\' . $class, $routes[$route_name]['defaults']['_form']);
      $this->assertSame('\\' . $class . '::access', $routes[$route_name]['requirements']['_custom_access']);
      $this->assertSame('workbc_ssot.uploads', $links[$route_name]['parent']);
      $this->assertArrayNotHasKey($permission, $forms);
      $forms[$permission] = $form;
    }
    $this->assertNotEmpty($forms);

    $this->assertSame('workbc_ssot.upload_access:access', $routes['workbc_ssot.uploads']['requirements']['_custom_access']);
    $this->assertSame(SsotUploadAccess::class, $services['services']['workbc_ssot.upload_access']['class']);
    $this->assertSame(['@user.permissions'], $services['services']['workbc_ssot.upload_access']['arguments']);
    $this->assertSame('view.ssot_log.page_1', $links['workbc_ssot.main']['route_name']);
    $this->assertSame('workbc_custom.admin_index', $links['workbc_ssot.uploads']['parent']);
    $this->assertSame('workbc_ssot.uploads', $links['workbc_ssot.uploads']['route_name']);
    $this->assertSame('administer ssot settings', $log['display']['default']['display_options']['access']['options']['perm']);
    $this->assertSame('administer ssot settings', $routes['workbc_ssot.refresh']['requirements']['_permission']);

    $checker = new PermissionAccessCheck();
    foreach ($permissions as &$definition) {
      $definition['provider'] = 'workbc_ssot';
    }
    unset($definition);
    $handler = $this->createMock(PermissionHandlerInterface::class);
    $handler->method('getPermissions')->willReturn($permissions);
    $landing_access = new SsotUploadAccess($handler);
    $log_route = new Route('/log', [], ['_permission' => $log['display']['default']['display_options']['access']['options']['perm']]);
    foreach (array_merge([[], ['administer ssot settings'], ['access administration pages']], array_map(fn($p) => [$p], array_keys($forms))) as $granted) {
      $account = $this->account($granted);
      $landing_result = $landing_access->access($account);
      $this->assertSame((bool) array_intersect($granted, array_merge(['administer ssot settings'], array_keys($forms))), $landing_result->isAllowed());
      $this->assertSame(in_array('administer ssot settings', $granted, TRUE), $checker->access($log_route, $account)->isAllowed());
      $this->assertContains('user.permissions', $landing_result->getCacheContexts());
      foreach ($forms as $permission => $form) {
        $result = $form->access($account);
        $this->assertSame(in_array('administer ssot settings', $granted, TRUE) || in_array($permission, $granted, TRUE), $result->isAllowed());
        $this->assertContains('user.permissions', $result->getCacheContexts());
      }
    }
  }

  public function testFutureFormUsesItsOwnPermission(): void {
    $form = new TestGenericForm();
    $this->assertTrue($form->access($this->account(['upload ssot data sheet generic']))->isAllowed());
    $this->assertFalse($form->access($this->account(['upload ssot data sheet labour_force_survey']))->isAllowed());
  }

  public function testLandingDiscoversNewSheetPermissions(): void {
    $handler = $this->createMock(PermissionHandlerInterface::class);
    $handler->method('getPermissions')->willReturn([
      'upload ssot data sheet generic' => ['provider' => 'workbc_ssot'],
      'upload ssot data sheet other' => ['provider' => 'other_module'],
      'other permission' => ['provider' => 'workbc_ssot'],
    ]);
    $access = new SsotUploadAccess($handler);

    $this->assertTrue($access->access($this->account(['upload ssot data sheet generic']))->isAllowed());
    $this->assertTrue($access->access($this->account(['administer ssot settings']))->isAllowed());
    foreach (['upload ssot data sheet other', 'upload ssot data sheet unregistered', 'other permission'] as $permission) {
      $this->assertFalse($access->access($this->account([$permission]))->isAllowed());
    }
  }

}
