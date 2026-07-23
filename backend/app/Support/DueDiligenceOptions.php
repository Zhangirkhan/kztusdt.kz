<?php

declare(strict_types=1);

namespace App\Support;

final class DueDiligenceOptions
{
  /**
   * @return list<string>
   */
  public static function sourceOfFunds(): array
  {
    return [
      'salary',
      'business_income',
      'self_employed',
      'investments',
      'crypto_trading',
      'property_sale',
      'inheritance',
      'savings',
      'gift',
      'other',
    ];
  }

  /**
   * @return list<string>
   */
  public static function occupations(): array
  {
    return [
      'employee',
      'self_employed',
      'sole_proprietor',
      'business_owner',
      'executive',
      'student',
      'unemployed',
      'retired',
    ];
  }

  /**
   * @return list<string>
   */
  public static function industries(): array
  {
    return [
      'finance',
      'it',
      'trade',
      'construction',
      'real_estate',
      'manufacturing',
      'government',
      'crypto',
      'other',
    ];
  }

  /**
   * @return list<string>
   */
  public static function annualIncomes(): array
  {
    return [
      'under_10k',
      '10k_50k',
      '50k_100k',
      '100k_500k',
      'over_500k',
    ];
  }

  /**
   * @return list<string>
   */
  public static function platformPurposes(): array
  {
    return [
      'investments',
      'spot_trading',
      'futures',
      'staking',
      'transfers',
      'payments',
      'other',
    ];
  }

  /**
   * @return array<string, list<string>>
   */
  public static function forFrontend(): array
  {
    return [
      'sourceOfFunds' => self::sourceOfFunds(),
      'occupations' => self::occupations(),
      'industries' => self::industries(),
      'annualIncomes' => self::annualIncomes(),
      'platformPurposes' => self::platformPurposes(),
    ];
  }
}
