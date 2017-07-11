<?hh // strict
/*
 *  Copyright (c) 2004-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the BSD-style license found in the
 *  LICENSE file in the root directory of this source tree. An additional grant
 *  of patent rights can be found in the PATENTS file in the same directory.
 *
 */

use HH\Lib\Vec as VecHSL;
use HH\Lib\{C, Math, Str};
use function \Facebook\FBExpect\expect;
// @oss-disable: use InvariantViolationException as InvariantException;

/**
 * @emails oncall+hack_prod_infra
 */
final class VecOrderTest extends PHPUnit_Framework_TestCase {

  public static function provideTestRange(): array<mixed> {
    return array(
      tuple(1, 10, null, vec[1, 2, 3, 4, 5, 6, 7, 8, 9, 10]),
      tuple(1, 10, 1, vec[1, 2, 3, 4, 5, 6, 7, 8, 9, 10]),
      tuple(1, 10, 2, vec[1, 3, 5, 7, 9]),
      tuple(1, 10, 3, vec[1, 4, 7, 10]),
      tuple(1, 10, 9, vec[1, 10]),
      tuple(10, 1, null, vec[10, 9, 8, 7, 6, 5, 4, 3, 2, 1]),
      tuple(10, 1, 5, vec[10, 5]),
      tuple(
        1.0,
        2.0,
        0.1,
        vec[1.0, 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 2.0]
      ),
      tuple(3.0, -1.5, 1.5, vec[3.0, 1.5, 0.0, -1.5]),
      tuple(
        -4294967296.0,
        -4294968548.5,
        125.25,
        vec[
          -4294967296.0,
          -4294967421.25,
          -4294967546.5,
          -4294967671.75,
          -4294967797.0,
          -4294967922.25,
          -4294968047.5,
          -4294968172.75,
          -4294968298.0,
          -4294968423.25,
          -4294968548.5,
        ],
      ),
      tuple(1, 1, 1, vec[1]),
      tuple(1, 1, 2, vec[1]),
      tuple(3.5, 3.5, 1.0, vec[3.5]),
      tuple(1, 10, 11, vec[1]),
      tuple(3.5, 7.5, 6.0, vec[3.5]),
      tuple(-3.5, -7.5, 6.0, vec[-3.5]),
      tuple(-4, 4, 3, vec[-4, -1, 2]),
      tuple(-2, 2, 5, vec[-2]),
      tuple(4, -4, 3, vec[4, 1, -2]),
      tuple(2, -2, 5, vec[2]),
    );
  }

  /** @dataProvider provideTestRange */
  public function testRange<Tv as num>(
    Tv $start,
    Tv $end,
    ?Tv $increment,
    vec<Tv> $expected,
  ): void {
    $actual = VecHSL\range($start, $end, $increment);
    expect(C\count($actual))->toBeSame(C\count($expected));
    for ($i = 0; $i < C\count($actual); $i++) {
      expect((float) $actual[$i])->toAlmostEqual((float) $expected[$i]);
    }
  }

  public static function provideTestRangeException(): array<mixed> {
    return array(
      tuple(0, 1, 0),
      tuple(-10, 10, -30),
    );
  }

  /** @dataProvider provideTestRangeException */
  public function testRangeException<Tv as num>(
    Tv $start,
    Tv $end,
    Tv $increment,
  ): void {
    expect(() ==> VecHSL\range($start, $end, $increment))
      ->toThrow(InvariantException::class);
  }

  public static function provideTestReverse(): array<mixed> {
    return array(
      tuple(
        vec[1, 2, 3, 4, 5],
        vec[5, 4, 3, 2, 1],
      ),
      tuple(
        Vector {'the', 'quick', 'brown', 'fox', 'jumped'},
        vec['jumped', 'fox', 'brown', 'quick', 'the'],
      ),
      tuple(
        HackLibTestTraversables::getIterator(range(1, 5)),
        vec[5, 4, 3, 2, 1],
      ),
    );
  }

  /** @dataProvider provideTestReverse */
  public function testReverse<Tv>(
    Traversable<Tv> $traversable,
    vec<Tv> $expected,
  ): void {
    expect(VecHSL\reverse($traversable))->toBeSame($expected);
  }

  public static function provideTestShuffle(): array<mixed> {
    return array(
      tuple(
        vec[8, 6, 7, 5, 3, 0, 9],
        vec[0, 3, 5, 6, 7, 8, 9],
      ),
      tuple(
        HackLibTestTraversables::getIterator(array(8, 6, 7, 5, 3, 0, 9)),
        vec[0, 3, 5, 6, 7, 8, 9],
      ),
    );
  }

  /** @dataProvider provideTestShuffle */
  public function testShuffle<Tv>(
    Traversable<Tv> $traversable,
    vec<Tv> $expected,
  ): void {
    if (!class_exists('FlibAutoloadMap')) {
      // UNSAFE_BLOCK (internal PHPUnit uses static::, OSS uses $this->)
      $this->markTestSkipped(
        "Mocking is not supported externally",
      );
      return;
    }

    try {
      {
        // UNSAFE_BLOCK: flib IntegrationTest doesn't exist in open source
        \IntegrationTest::mockFunctionStatic(fun('shuffle'))
          ->mockImplementation(fun('sort'));
      }

      $shuffled = VecHSL\shuffle($traversable);
      expect($shuffled)->toBeSame($expected);
    } finally {
      // UNSAFE_BLOCK: flib IntegrationTest doesn't exist in open source
      \IntegrationTest::unmockFunctionStatic(fun('shuffle'));
    }
  }

  public static function provideTestSort(): array<mixed> {
    return array(
      tuple(
        vec['the', 'quick', 'brown', 'fox'],
        null,
        vec['brown', 'fox', 'quick', 'the'],
      ),
      tuple(
        vec['the', 'quick', 'brown', 'fox'],
        /* HH_FIXME[1002] Spaceship operator */
        ($a, $b) ==> $a[1] <=> $b[1],
        vec['the', 'fox', 'brown', 'quick'],
      ),
      tuple(
        Vector {1, 1.2, -5.7, -5.8},
        null,
        vec[-5.8, -5.7, 1, 1.2],
      ),
      tuple(
        HackLibTestTraversables::getIterator(array(8, 6, 7, 5, 3, 0, 9)),
        null,
        vec[0, 3, 5, 6, 7, 8, 9],
      ),
    );
  }

  /** @dataProvider provideTestSort */
  public function testSort<Tv>(
    Traversable<Tv> $traversable,
    ?(function(Tv, Tv): int) $comparator,
    vec<Tv> $expected,
  ): void {
    expect(VecHSL\sort($traversable, $comparator))->toBeSame($expected);
  }

  public static function provideTestSortBy(): array<mixed> {
    return array(
      tuple(
        array('the', 'quick', 'brown', 'fox', 'jumped'),
        fun('strrev'),
        null,
        vec['jumped', 'the', 'quick', 'brown', 'fox'],
      ),
      tuple(
        HackLibTestTraversables::getIterator(
          array('the', 'quick', 'brown', 'fox', 'jumped'),
        ),
        fun('strrev'),
        null,
        vec['jumped', 'the', 'quick', 'brown', 'fox'],
      ),
      tuple(
        Vector {'the', 'quick', 'brown', 'fox', 'jumped'},
        fun('strrev'),
        ($a, $b) ==> $b <=> $a,
        vec['fox', 'brown', 'quick', 'the', 'jumped'],
      ),
      tuple(
        vec['the', 'quick', 'brown', 'fox', 'jumped'],
        () ==> 0,
        null,
        vec['the', 'quick', 'brown', 'fox', 'jumped'],
      ),
      tuple(
        Vector {'the', 'quick', 'brown', 'fox', 'jumped', 'over'},
        $x ==> Str\length($x),
        null,
        vec['the', 'fox', 'over', 'quick', 'brown', 'jumped'],
      ),
      tuple(
        Vector {'the', 'quick', 'fox', 'jumped', 'over'},
        $x ==> Str\length($x) / 2,
        null,
        vec['the', 'fox', 'over', 'quick', 'jumped'],
      ),
    );
  }

  /** @dataProvider provideTestSortBy */
  public function testSortBy<Tv, Ts>(
    Traversable<Tv> $traversable,
    (function(Tv): Ts) $scalar_func,
    ?(function(Ts, Ts): int) $comparator,
    vec<Tv> $expected,
  ): void {
    expect(VecHSL\sort_by($traversable, $scalar_func, $comparator))
      ->toBeSame($expected);
  }
}