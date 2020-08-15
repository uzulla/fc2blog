<?php
declare(strict_types=1);

namespace Fc2blog\Tests\App\Core\Cookie;

use Cookie;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TypeError;

class SetTest extends TestCase
{
  public function testSetString(): void
  {
    // Cookieの正しいテストはUnitTestでは困難です
    // echo '<?php setcookie("1","b");' | php-cgi
    // 等でテストができますが、php-cgiがない環境でテストが実行できません。
    @\Fc2blog\Web\Cookie::set("k", "v");
    $this->assertTrue(true);
  }

  public function testSetInvalidType(): void
  {
    try {
      /** @noinspection PhpStrictTypeCheckingInspection */
      \Fc2blog\Web\Cookie::set(true, "v");
      $this->fail();
    } catch (TypeError $e) {
      $this->assertInstanceOf(TypeError::class, $e);
    }

    try {
      /** @noinspection PhpStrictTypeCheckingInspection */
      \Fc2blog\Web\Cookie::set(1, "v");
      $this->fail();
    } catch (TypeError $e) {
      $this->assertInstanceOf(TypeError::class, $e);
    }

    try {
      /** @noinspection PhpStrictTypeCheckingInspection */
      \Fc2blog\Web\Cookie::set("k", true);
      $this->fail();
    } catch (TypeError $e) {
      $this->assertInstanceOf(TypeError::class, $e);
    }

    try {
      /** @noinspection PhpStrictTypeCheckingInspection */
      \Fc2blog\Web\Cookie::set("k", 1);
      $this->fail();
    } catch (TypeError $e) {
      $this->assertInstanceOf(TypeError::class, $e);
    }
  }

  public function testDenyInvalidSamesite(): void
  {
    try {
      @\Fc2blog\Web\Cookie::set("k", "v", time(), "", "", false, false, "Lax");
      $this->assertTrue(true);
    } catch (InvalidArgumentException $e) {
      $this->fail($e->getMessage());
    }
    try {
      @\Fc2blog\Web\Cookie::set("k", "v", time(), "", "", false, false, "Strict");
      $this->assertTrue(true);
    } catch (InvalidArgumentException $e) {
      $this->fail($e->getMessage());
    }
    try {
      $_SERVER['HTTPS'] = "on";
      @\Fc2blog\Web\Cookie::set("k", "v", time(), "", "", true, false, "None");
      $this->assertTrue(true);
    } catch (InvalidArgumentException $e) {
      $this->fail($e->getMessage());
    } finally {
      unset($_SERVER['HTTPS']);
    }
    try {
      @\Fc2blog\Web\Cookie::set("k", "v", time(), "", "", false, false, "Wrong");
      $this->fail();
    } catch (InvalidArgumentException $e) {
      $this->assertTrue(true);
    }
  }

  public function testSamesiteNoneAndNotSecure(): void
  {
    try {
      unset($_SERVER['HTTPS']);
      @\Fc2blog\Web\Cookie::set("k", "v", time(), "", "", true, false, "None");
      $this->fail();
    } catch (InvalidArgumentException $e) {
      $this->assertTrue(true);
    }

    try {
      $_SERVER['HTTPS'] = "on";
      @\Fc2blog\Web\Cookie::set("k", "v", time(), "", "", false, false, "None");
      $this->fail();
    } catch (InvalidArgumentException $e) {
      $this->assertTrue(true);
    } finally {
      unset($_SERVER['HTTPS']);
    }

    try {
      $_SERVER['HTTPS'] = "on";
      @\Fc2blog\Web\Cookie::set("k", "v", time(), "", "", true, false, "None");
    } catch (InvalidArgumentException $e) {
      $this->fail($e->getMessage());
    } finally {
      unset($_SERVER['HTTPS']);
    }
  }
}
