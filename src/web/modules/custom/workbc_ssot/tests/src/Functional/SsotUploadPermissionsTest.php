<?php

namespace Drupal\Tests\workbc_ssot\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\views\Entity\View;
use Drupal\workbc_ssot\Plugin\views\filter\SsotDatasetFilter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests SSoT navigation and page access as real logged-in users.
 */
#[Group('workbc_ssot')]
#[RunTestsInSeparateProcesses]
class SsotUploadPermissionsTest extends BrowserTestBase {

  protected static $modules = ['workbc_ssot', 'workbc_custom', 'node', 'file', 'path_alias', 'views', 'block', 'big_pipe', 'mobile_detect'];

  protected $defaultTheme = 'stark';

  protected bool $useOneTimeLoginLinks = FALSE;

  // The existing log view has schema gaps unrelated to permission checks.
  protected static $configSchemaCheckerExclusions = ['views.view.ssot_log'];

  public function installDrupal(): void {
    parent::installDrupal();
    $this->writeSettings([
      'config' => [
        'workbc' => (object) [
          'value' => ['paths' => [
            'career_exploration' => '/explore-careers',
            'career_exploration_search' => '/explore-careers/search',
            'career_trek' => '/career-trek',
          ]],
          'required' => TRUE,
        ],
      ],
    ]);
  }

  protected function setUp(): void {
    parent::setUp();

    // Do not let the authenticated role supply extra permissions.
    $role = Role::load(Role::AUTHENTICATED_ID);
    $role->set('permissions', [])->save();

    $module_path = $this->root . '/' . $this->container->get('extension.list.module')->getPath('workbc_ssot');
    $view = Yaml::parseFile(dirname($module_path, 4) . '/config/sync/views.view.ssot_log.yml');
    unset($view['uuid']);
    View::create($view)->save();

    // Log access tests do not need to call the external SSoT API for options.
    $this->container->get('cache.default')->set(SsotDatasetFilter::CACHE_ID, []);
    $this->container->get('router.builder')->rebuild();
    $this->drupalPlaceBlock('local_tasks_block');
  }

  public function testSingleSheetPermissions(): void {
    $module_path = $this->container->get('extension.list.module')->getPath('workbc_ssot');
    $routes = Yaml::parseFile("$module_path/workbc_ssot.routing.yml");
    $links = Yaml::parseFile("$module_path/workbc_ssot.links.menu.yml");
    $sheets = [];
    foreach ($routes as $route_name => $route) {
      $class = $route['defaults']['_form'] ?? NULL;
      if ($class && is_subclass_of($class, \Drupal\workbc_ssot\Form\SsotUploadFormBase::class)) {
        $form = new $class();
        $id = (new \ReflectionClass($form))->getProperty('id')->getValue($form);
        $sheets[$id] = ['path' => $route['path'], 'title' => $links[$route_name]['title']];
      }
    }
    $this->assertNotEmpty($sheets);

    foreach ($sheets as $id => $sheet) {
      $permission = 'upload ssot data sheet ' . $id;
      $user = $this->drupalCreateUser([$permission]);
      $granted = [];
      foreach (Role::loadMultiple($user->getRoles()) as $role) {
        $granted = array_merge($granted, $role->getPermissions());
      }
      $this->assertEqualsCanonicalizing([$permission], array_unique($granted));
      $this->drupalLogin($user);

      $this->drupalGet('/admin/config/workbc/ssot/upload');
      $this->assertSession()->statusCodeEquals(200);
      foreach ($sheets as $other_id => $other_sheet) {
        if ($id === $other_id) {
          $this->assertSession()->linkExists($other_sheet['title']);
        }
        else {
          $this->assertSession()->linkNotExists($other_sheet['title']);
        }
      }
      $this->assertSession()->linkNotExists('Upload Log');
      $this->assertSession()->linkNotExists('Refresh Datasets');
      $this->clickLink($sheet['title']);
      $this->assertSession()->statusCodeEquals(200);
      $this->assertSession()->fieldExists('Year');
      $this->assertSession()->buttonExists('Submit');

      foreach ($sheets as $other_id => $other_sheet) {
        $this->drupalGet($other_sheet['path']);
        $this->assertSession()->statusCodeEquals($id === $other_id ? 200 : 403);
      }
      foreach (['/admin/config/workbc/ssot/log', '/admin/config/workbc/ssot/refresh', '/admin/config/workbc'] as $path) {
        $this->drupalGet($path);
        $this->assertSession()->statusCodeEquals(403);
      }
      $this->drupalLogout();
    }
  }

  public function testNoUploadPermission(): void {
    $this->drupalLogin($this->drupalCreateUser([]));
    foreach ([
      '/admin/config/workbc/ssot/upload',
      '/admin/config/workbc/ssot/monthly-labour-market-updates',
      '/admin/config/workbc/ssot/industry-outlook',
      '/admin/config/workbc/ssot/labour-force-survey',
      '/admin/config/workbc/ssot/log',
      '/admin/config/workbc/ssot/refresh',
    ] as $path) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(403);
    }
  }

  public function testAdministratorPermission(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer ssot settings']));
    $this->drupalGet('/admin/config/workbc/ssot/upload');
    $this->assertSession()->statusCodeEquals(200);
    foreach (['Labour Market Monthly Update', 'Industry Profile Yearly Update', 'Labour Force Survey Yearly Update', 'Upload Log', 'Refresh Datasets'] as $title) {
      $this->assertSession()->linkExists($title);
    }
    foreach ([
      '/admin/config/workbc/ssot/monthly-labour-market-updates',
      '/admin/config/workbc/ssot/industry-outlook',
      '/admin/config/workbc/ssot/labour-force-survey',
      '/admin/config/workbc/ssot/log',
      '/admin/config/workbc/ssot/refresh',
    ] as $path) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(200);
    }
  }

}
