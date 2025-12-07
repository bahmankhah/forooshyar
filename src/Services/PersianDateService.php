<?php

namespace Forooshyar\Services;

/**
 * Persian Date and Time Service
 * Handles Jalali calendar conversion and Persian number formatting
 */
class PersianDateService
{
    /**
     * Persian month names
     */
    private const PERSIAN_MONTHS = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
        5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
        9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
    ];

    /**
     * Persian day names
     */
    private const PERSIAN_DAYS = [
        0 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه',
        4 => 'پنج‌شنبه', 5 => 'جمعه', 6 => 'شنبه'
    ];

    /**
     * Persian digits
     */
    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    private const ENGLISH_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /**
     * Convert Gregorian date to Jalali
     */
    public function gregorianToJalali($gYear, $gMonth, $gDay): array
    {
        $gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        
        if ($gYear <= 1600) {
            $gYear = 621;
            $gMonth = 3;
            $gDay = 22;
        }
        
        if ($gYear % 4 === 0 && ($gYear % 100 !== 0 || $gYear % 400 === 0)) {
            $gDaysInMonth[1] = 29;
        }
        
        $totalDays = 365 * $gYear + floor(($gYear + 3) / 4) - floor(($gYear + 99) / 100) + floor(($gYear + 399) / 400) - 80 + $gDay;
        
        for ($i = 0; $i < $gMonth - 1; $i++) {
            $totalDays += $gDaysInMonth[$i];
        }
        
        $jYear = -14;
        $totalDays -= 79;
        
        $jYearCycle = 33;
        $jYearCycleDays = 12053;
        
        $cycles = floor($totalDays / $jYearCycleDays);
        $jYear += $cycles * $jYearCycle;
        $totalDays %= $jYearCycleDays;
        
        $auxYear = 0;
        while ($auxYear < $jYearCycle && $totalDays >= $this->jalaliYearDays($jYear + $auxYear)) {
            $totalDays -= $this->jalaliYearDays($jYear + $auxYear);
            $auxYear++;
        }
        
        $jYear += $auxYear;
        
        $i = 0;
        while ($i < 12 && $totalDays >= $this->jalaliMonthDays($jYear, $i + 1)) {
            $totalDays -= $this->jalaliMonthDays($jYear, $i + 1);
            $i++;
        }
        
        $jMonth = $i + 1;
        $jDay = $totalDays + 1;
        
        return [$jYear, $jMonth, $jDay];
    }

    /**
     * Check if Jalali year is leap
     */
    public function isLeapYear($year): bool
    {
        $breaks = [
            -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
            1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178
        ];
        
        $jp = $breaks[0];
        $jump = 0;
        
        for ($j = 1; $j <= count($breaks); $j++) {
            $jm = $breaks[$j] ?? 0;
            $jump = $jm - $jp;
            
            if ($year < $jm) {
                break;
            }
            
            $jp = $jm;
        }
        
        $n = $year - $jp;
        
        if ($n < $jump) {
            if ($jump - $n < 6) {
                $n = $n - $jump + floor(($jump + 4) / 6) * 6;
            }
            
            $leap = (($n + 1) % 33) % 4;
            
            if ($jump === 33 && $leap === 1) {
                $leap = 0;
            }
            
            return $leap === 1;
        }
        
        return false;
    }

    /**
     * Get number of days in Jalali year
     */
    private function jalaliYearDays($year): int
    {
        return $this->isLeapYear($year) ? 366 : 365;
    }

    /**
     * Get number of days in Jalali month
     */
    private function jalaliMonthDays($year, $month): int
    {
        if ($month <= 6) {
            return 31;
        } elseif ($month <= 11) {
            return 30;
        } else {
            return $this->isLeapYear($year) ? 30 : 29;
        }
    }

    /**
     * Format Jalali date
     */
    public function formatJalali($date, $format = 'Y/m/d'): string
    {
        if (is_string($date)) {
            $date = new \DateTime($date);
        } elseif (!($date instanceof \DateTime)) {
            $date = new \DateTime();
        }
        
        [$jYear, $jMonth, $jDay] = $this->gregorianToJalali(
            (int)$date->format('Y'),
            (int)$date->format('n'),
            (int)$date->format('j')
        );
        
        $dayOfWeek = (int)$date->format('w');
        
        $replacements = [
            'Y' => $jYear,
            'y' => substr($jYear, -2),
            'm' => str_pad($jMonth, 2, '0', STR_PAD_LEFT),
            'n' => $jMonth,
            'd' => str_pad($jDay, 2, '0', STR_PAD_LEFT),
            'j' => $jDay,
            'F' => self::PERSIAN_MONTHS[$jMonth],
            'M' => mb_substr(self::PERSIAN_MONTHS[$jMonth], 0, 3),
            'l' => self::PERSIAN_DAYS[$dayOfWeek],
            'D' => mb_substr(self::PERSIAN_DAYS[$dayOfWeek], 0, 2),
            'H' => $date->format('H'),
            'i' => $date->format('i'),
            's' => $date->format('s'),
            'A' => $date->format('H') < 12 ? 'ق.ظ' : 'ب.ظ',
            'a' => $date->format('H') < 12 ? 'ق.ظ' : 'ب.ظ'
        ];
        
        $formatted = $format;
        foreach ($replacements as $key => $value) {
            $formatted = str_replace($key, $value, $formatted);
        }
        
        return $this->toPersianDigits($formatted);
    }

    /**
     * Convert English digits to Persian
     */
    public function toPersianDigits($string): string
    {
        return str_replace(self::ENGLISH_DIGITS, self::PERSIAN_DIGITS, $string);
    }

    /**
     * Convert Persian digits to English
     */
    public function toEnglishDigits($string): string
    {
        return str_replace(self::PERSIAN_DIGITS, self::ENGLISH_DIGITS, $string);
    }

    /**
     * Format Persian number with separators
     */
    public function formatPersianNumber($number, $decimals = 0, $thousandSeparator = '،', $decimalSeparator = '٫'): string
    {
        $formatted = number_format($number, $decimals, $decimalSeparator, $thousandSeparator);
        return $this->toPersianDigits($formatted);
    }

    /**
     * Get current Jalali date
     */
    public function now($format = 'Y/m/d'): string
    {
        return $this->formatJalali(new \DateTime(), $format);
    }

    /**
     * Get current Jalali date and time
     */
    public function nowWithTime($format = 'l، j F Y - H:i'): string
    {
        return $this->formatJalali(new \DateTime(), $format);
    }

    /**
     * Get relative time in Persian
     */
    public function getRelativeTime($date): string
    {
        if (is_string($date)) {
            $date = new \DateTime($date);
        }
        
        $now = new \DateTime();
        $diff = $now->diff($date);
        
        if ($diff->days > 0) {
            if ($diff->days === 1) {
                return $diff->invert ? 'دیروز' : 'فردا';
            } elseif ($diff->days < 7) {
                return $diff->invert 
                    ? $this->toPersianDigits($diff->days) . ' روز پیش'
                    : $this->toPersianDigits($diff->days) . ' روز دیگر';
            } elseif ($diff->days < 30) {
                $weeks = floor($diff->days / 7);
                return $diff->invert 
                    ? $this->toPersianDigits($weeks) . ' هفته پیش'
                    : $this->toPersianDigits($weeks) . ' هفته دیگر';
            } else {
                return $this->formatJalali($date, 'j F Y');
            }
        } elseif ($diff->h > 0) {
            return $diff->invert 
                ? $this->toPersianDigits($diff->h) . ' ساعت پیش'
                : $this->toPersianDigits($diff->h) . ' ساعت دیگر';
        } elseif ($diff->i > 0) {
            return $diff->invert 
                ? $this->toPersianDigits($diff->i) . ' دقیقه پیش'
                : $this->toPersianDigits($diff->i) . ' دقیقه دیگر';
        } else {
            return 'همین الان';
        }
    }

    /**
     * Format file size in Persian
     */
    public function formatFileSize($bytes): string
    {
        $units = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return $this->formatPersianNumber(round($bytes, 2), 2) . ' ' . $units[$i];
    }

    /**
     * Format duration in Persian
     */
    public function formatDuration($seconds): string
    {
        if ($seconds < 60) {
            return $this->toPersianDigits($seconds) . ' ثانیه';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            
            $result = $this->toPersianDigits($minutes) . ' دقیقه';
            if ($remainingSeconds > 0) {
                $result .= ' و ' . $this->toPersianDigits($remainingSeconds) . ' ثانیه';
            }
            
            return $result;
        } else {
            $hours = floor($seconds / 3600);
            $remainingMinutes = floor(($seconds % 3600) / 60);
            
            $result = $this->toPersianDigits($hours) . ' ساعت';
            if ($remainingMinutes > 0) {
                $result .= ' و ' . $this->toPersianDigits($remainingMinutes) . ' دقیقه';
            }
            
            return $result;
        }
    }

    /**
     * Get Persian month name
     */
    public function getPersianMonthName($month): string
    {
        return self::PERSIAN_MONTHS[$month] ?? '';
    }

    /**
     * Get Persian day name
     */
    public function getPersianDayName($day): string
    {
        return self::PERSIAN_DAYS[$day] ?? '';
    }

    /**
     * Validate Persian date
     */
    public function isValidPersianDate($year, $month, $day): bool
    {
        if ($month < 1 || $month > 12) {
            return false;
        }
        
        if ($day < 1) {
            return false;
        }
        
        $maxDays = $this->jalaliMonthDays($year, $month);
        
        return $day <= $maxDays;
    }

    /**
     * Get Persian calendar info for a specific date
     */
    public function getCalendarInfo($date = null): array
    {
        if ($date === null) {
            $date = new \DateTime();
        } elseif (is_string($date)) {
            $date = new \DateTime($date);
        }
        
        [$jYear, $jMonth, $jDay] = $this->gregorianToJalali(
            (int)$date->format('Y'),
            (int)$date->format('n'),
            (int)$date->format('j')
        );
        
        $dayOfWeek = (int)$date->format('w');
        
        return [
            'jalali' => [
                'year' => $jYear,
                'month' => $jMonth,
                'day' => $jDay,
                'month_name' => self::PERSIAN_MONTHS[$jMonth],
                'day_name' => self::PERSIAN_DAYS[$dayOfWeek],
                'is_leap_year' => $this->isLeapYear($jYear),
                'days_in_month' => $this->jalaliMonthDays($jYear, $jMonth)
            ],
            'gregorian' => [
                'year' => (int)$date->format('Y'),
                'month' => (int)$date->format('n'),
                'day' => (int)$date->format('j'),
                'month_name' => $date->format('F'),
                'day_name' => $date->format('l')
            ],
            'formatted' => [
                'short' => $this->formatJalali($date, 'Y/m/d'),
                'medium' => $this->formatJalali($date, 'j F Y'),
                'long' => $this->formatJalali($date, 'l، j F Y'),
                'with_time' => $this->formatJalali($date, 'l، j F Y - H:i'),
                'relative' => $this->getRelativeTime($date)
            ]
        ];
    }
}