/**
 * Forooshyar Admin Persian Utilities
 * Persian number formatting, Jalali calendar, and RTL enhancements
 */

(function($) {
    'use strict';

    // Persian/Farsi digit mapping
    const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    const englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    // Persian month names
    const persianMonths = [
        'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
    ];

    // Persian day names
    const persianDays = [
        'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه', 'شنبه'
    ];

    /**
     * Persian Number Utilities
     */
    const PersianNumber = {
        /**
         * Convert English digits to Persian
         */
        toPersian: function(str) {
            if (typeof str !== 'string') {
                str = String(str);
            }
            
            for (let i = 0; i < englishDigits.length; i++) {
                str = str.replace(new RegExp(englishDigits[i], 'g'), persianDigits[i]);
            }
            
            return str;
        },

        /**
         * Convert Persian digits to English
         */
        toEnglish: function(str) {
            if (typeof str !== 'string') {
                str = String(str);
            }
            
            for (let i = 0; i < persianDigits.length; i++) {
                str = str.replace(new RegExp(persianDigits[i], 'g'), englishDigits[i]);
            }
            
            return str;
        },

        /**
         * Format number with Persian separators
         */
        format: function(num, options = {}) {
            const defaults = {
                usePersianDigits: true,
                useThousandSeparator: true,
                thousandSeparator: '،',
                decimalSeparator: '٫'
            };
            
            const opts = Object.assign(defaults, options);
            
            if (typeof num !== 'number') {
                num = parseFloat(num);
            }
            
            if (isNaN(num)) {
                return '';
            }
            
            let formatted = num.toString();
            
            // Add thousand separators
            if (opts.useThousandSeparator) {
                const parts = formatted.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, opts.thousandSeparator);
                
                if (parts[1]) {
                    formatted = parts[0] + opts.decimalSeparator + parts[1];
                } else {
                    formatted = parts[0];
                }
            }
            
            // Convert to Persian digits
            if (opts.usePersianDigits) {
                formatted = this.toPersian(formatted);
            }
            
            return formatted;
        }
    };

    /**
     * Jalali Calendar Utilities
     */
    const JalaliCalendar = {
        /**
         * Check if Jalali year is leap
         */
        isLeapYear: function(year) {
            const breaks = [
                -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
                1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178
            ];
            
            let jp = breaks[0];
            let jump = 0;
            
            for (let j = 1; j <= breaks.length; j++) {
                const jm = breaks[j];
                jump = jm - jp;
                
                if (year < jm) {
                    break;
                }
                
                jp = jm;
            }
            
            let n = year - jp;
            
            if (n < jump) {
                if (jump - n < 6) {
                    n = n - jump + ((jump + 4) / 6) * 6;
                }
                
                let leap = ((n + 1) % 33) % 4;
                
                if (jump === 33 && leap === 1) {
                    leap = 0;
                }
                
                return leap === 1;
            }
            
            return false;
        },

        /**
         * Convert Gregorian to Jalali
         */
        gregorianToJalali: function(gYear, gMonth, gDay) {
            const gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
            
            if (gYear <= 1600) {
                gYear = 621;
                gMonth = 3;
                gDay = 22;
            }
            
            if (gYear % 4 === 0 && (gYear % 100 !== 0 || gYear % 400 === 0)) {
                gDaysInMonth[1] = 29;
            }
            
            let totalDays = 365 * gYear + Math.floor((gYear + 3) / 4) - Math.floor((gYear + 99) / 100) + Math.floor((gYear + 399) / 400) - 80 + gDay;
            
            for (let i = 0; i < gMonth - 1; i++) {
                totalDays += gDaysInMonth[i];
            }
            
            let jYear = -14;
            let jMonth = 1;
            let jDay = 1;
            
            totalDays -= 79;
            
            const jYearCycle = 33;
            const jYearCycleDays = 12053;
            
            const cycles = Math.floor(totalDays / jYearCycleDays);
            jYear += cycles * jYearCycle;
            totalDays %= jYearCycleDays;
            
            let auxYear = 0;
            while (auxYear < jYearCycle && totalDays >= this.jalaliYearDays(jYear + auxYear)) {
                totalDays -= this.jalaliYearDays(jYear + auxYear);
                auxYear++;
            }
            
            jYear += auxYear;
            
            let i = 0;
            while (i < 12 && totalDays >= this.jalaliMonthDays(jYear, i + 1)) {
                totalDays -= this.jalaliMonthDays(jYear, i + 1);
                i++;
            }
            
            jMonth = i + 1;
            jDay = totalDays + 1;
            
            return [jYear, jMonth, jDay];
        },

        /**
         * Get number of days in Jalali year
         */
        jalaliYearDays: function(year) {
            return this.isLeapYear(year) ? 366 : 365;
        },

        /**
         * Get number of days in Jalali month
         */
        jalaliMonthDays: function(year, month) {
            if (month <= 6) {
                return 31;
            } else if (month <= 11) {
                return 30;
            } else {
                return this.isLeapYear(year) ? 30 : 29;
            }
        },

        /**
         * Format Jalali date
         */
        format: function(date, format = 'Y/m/d') {
            if (!(date instanceof Date)) {
                date = new Date(date);
            }
            
            const [jYear, jMonth, jDay] = this.gregorianToJalali(
                date.getFullYear(),
                date.getMonth() + 1,
                date.getDate()
            );
            
            const dayOfWeek = date.getDay();
            
            const replacements = {
                'Y': jYear,
                'y': jYear.toString().substr(-2),
                'm': jMonth.toString().padStart(2, '0'),
                'n': jMonth,
                'd': jDay.toString().padStart(2, '0'),
                'j': jDay,
                'F': persianMonths[jMonth - 1],
                'M': persianMonths[jMonth - 1].substr(0, 3),
                'l': persianDays[dayOfWeek],
                'D': persianDays[dayOfWeek].substr(0, 2)
            };
            
            let formatted = format;
            for (const [key, value] of Object.entries(replacements)) {
                formatted = formatted.replace(new RegExp(key, 'g'), value);
            }
            
            return PersianNumber.toPersian(formatted);
        },

        /**
         * Get current Jalali date
         */
        now: function(format = 'Y/m/d') {
            return this.format(new Date(), format);
        }
    };

    /**
     * RTL Text Direction Utilities
     */
    const RTLUtils = {
        /**
         * Detect if text contains RTL characters
         */
        hasRTL: function(text) {
            const rtlChars = /[\u0590-\u083F]|[\u08A0-\u08FF]|[\uFB1D-\uFDFF]|[\uFE70-\uFEFF]/mg;
            return rtlChars.test(text);
        },

        /**
         * Auto-detect and set text direction
         */
        autoDirection: function(element) {
            const $element = $(element);
            const text = $element.val() || $element.text();
            
            if (this.hasRTL(text)) {
                $element.attr('dir', 'rtl').addClass('forooshyar-rtl-text');
            } else {
                $element.attr('dir', 'ltr').addClass('forooshyar-ltr-text');
            }
        }
    };

    /**
     * Form Enhancement Utilities
     */
    const FormEnhancements = {
        /**
         * Initialize Persian number inputs
         */
        initPersianNumbers: function() {
            // Auto-convert numbers in designated fields
            $('.forooshyar-persian-number').on('input', function() {
                const $this = $(this);
                const value = $this.val();
                const englishValue = PersianNumber.toEnglish(value);
                
                // Store English value in data attribute for form submission
                $this.data('english-value', englishValue);
                
                // Display Persian formatted value
                if (!isNaN(parseFloat(englishValue))) {
                    $this.val(PersianNumber.format(parseFloat(englishValue)));
                }
            });

            // Convert back to English on form submission
            $('form').on('submit', function() {
                $('.forooshyar-persian-number').each(function() {
                    const $this = $(this);
                    const englishValue = $this.data('english-value');
                    if (englishValue !== undefined) {
                        $this.val(englishValue);
                    }
                });
            });
        },

        /**
         * Initialize auto-direction detection
         */
        initAutoDirection: function() {
            $('input[type="text"], textarea').on('input', function() {
                RTLUtils.autoDirection(this);
            });
        },

        /**
         * Initialize Jalali date displays
         */
        initJalaliDates: function() {
            $('.forooshyar-jalali-date').each(function() {
                const $this = $(this);
                const dateValue = $this.data('date') || $this.text();
                
                if (dateValue) {
                    const jalaliDate = JalaliCalendar.format(new Date(dateValue), 'l، j F Y');
                    $this.text(jalaliDate);
                }
            });
        },

        /**
         * Initialize Persian tooltips
         */
        initTooltips: function() {
            $('[data-tooltip-fa]').each(function() {
                const $this = $(this);
                const tooltip = $this.data('tooltip-fa');
                
                // Use native title attribute as fallback if jQuery UI tooltip is not available
                if ($.fn.tooltip) {
                    $this.attr('title', tooltip).tooltip({
                        position: { my: "right+15 center", at: "left center" },
                        content: tooltip
                    });
                } else {
                    $this.attr('title', tooltip);
                }
            });
        }
    };

    /**
     * Animation and UI Enhancements
     */
    const UIEnhancements = {
        /**
         * Initialize smooth animations
         */
        initAnimations: function() {
            // Fade in elements
            $('.forooshyar-fade-in').each(function(index) {
                $(this).delay(index * 100).animate({ opacity: 1 }, 300);
            });

            // Slide animations for tabs
            $('.forooshyar-nav-tabs .nav-tab').on('click', function() {
                const $content = $('.forooshyar-tab-content');
                $content.fadeOut(150, function() {
                    $content.fadeIn(150);
                });
            });
        },

        /**
         * Initialize interactive elements
         */
        initInteractivity: function() {
            // Button hover effects
            $('.button').on('mouseenter', function() {
                $(this).addClass('forooshyar-button-hover');
            }).on('mouseleave', function() {
                $(this).removeClass('forooshyar-button-hover');
            });

            // Form field focus effects
            $('input, textarea, select').on('focus', function() {
                $(this).closest('tr').addClass('forooshyar-field-focus');
            }).on('blur', function() {
                $(this).closest('tr').removeClass('forooshyar-field-focus');
            });
        },

        /**
         * Initialize loading states
         */
        initLoadingStates: function() {
            // Show loading on form submission
            $('form').on('submit', function() {
                const $submitBtn = $(this).find('button[type="submit"], input[type="submit"]');
                $submitBtn.prop('disabled', true).addClass('forooshyar-loading');
                
                // Re-enable after 5 seconds as fallback
                setTimeout(function() {
                    $submitBtn.prop('disabled', false).removeClass('forooshyar-loading');
                }, 5000);
            });

            // Show loading on AJAX requests
            $(document).ajaxStart(function() {
                $('.forooshyar-ajax-loader').show();
            }).ajaxStop(function() {
                $('.forooshyar-ajax-loader').hide();
            });
        }
    };

    /**
     * Accessibility Enhancements
     */
    const AccessibilityEnhancements = {
        /**
         * Initialize keyboard navigation
         */
        initKeyboardNav: function() {
            // Tab navigation for custom elements
            $('.forooshyar-field-checkbox').attr('tabindex', '0').on('keydown', function(e) {
                if (e.which === 13 || e.which === 32) { // Enter or Space
                    e.preventDefault();
                    $(this).find('input[type="checkbox"]').click();
                }
            });
        },

        /**
         * Initialize ARIA labels
         */
        initAriaLabels: function() {
            // Add ARIA labels to form fields
            $('input, textarea, select').each(function() {
                const $this = $(this);
                const $label = $('label[for="' + $this.attr('id') + '"]');
                
                if ($label.length) {
                    $this.attr('aria-labelledby', $label.attr('id') || 'label-' + $this.attr('id'));
                }
            });

            // Add ARIA descriptions
            $('.forooshyar-field-description').each(function() {
                const $this = $(this);
                const $field = $this.closest('td').find('input, textarea, select').first();
                
                if ($field.length) {
                    const descId = 'desc-' + ($field.attr('id') || Math.random().toString(36).substr(2, 9));
                    $this.attr('id', descId);
                    $field.attr('aria-describedby', descId);
                }
            });
        },

        /**
         * Initialize screen reader announcements
         */
        initScreenReader: function() {
            // Create live region for announcements
            if (!$('#forooshyar-announcements').length) {
                $('body').append('<div id="forooshyar-announcements" aria-live="polite" aria-atomic="true" class="sr-only"></div>');
            }
        },

        /**
         * Announce message to screen readers
         */
        announce: function(message) {
            $('#forooshyar-announcements').text(message);
        }
    };

    /**
     * Main initialization
     */
    const ForooshyarAdmin = {
        init: function() {
            $(document).ready(function() {
                // Initialize all components
                FormEnhancements.initPersianNumbers();
                FormEnhancements.initAutoDirection();
                FormEnhancements.initJalaliDates();
                FormEnhancements.initTooltips();
                
                UIEnhancements.initAnimations();
                UIEnhancements.initInteractivity();
                UIEnhancements.initLoadingStates();
                
                AccessibilityEnhancements.initKeyboardNav();
                AccessibilityEnhancements.initAriaLabels();
                AccessibilityEnhancements.initScreenReader();

                // Update timestamps every minute
                setInterval(function() {
                    FormEnhancements.initJalaliDates();
                }, 60000);

                // Initialize real-time clock
                ForooshyarAdmin.initClock();
            });
        },

        /**
         * Initialize real-time Jalali clock
         */
        initClock: function() {
            const updateClock = function() {
                const now = new Date();
                const jalaliDate = JalaliCalendar.format(now, 'l، j F Y');
                const time = PersianNumber.toPersian(
                    now.getHours().toString().padStart(2, '0') + ':' +
                    now.getMinutes().toString().padStart(2, '0')
                );
                
                $('.forooshyar-current-date').text(jalaliDate);
                $('.forooshyar-current-time').text(time);
            };

            // Update immediately and then every second
            updateClock();
            setInterval(updateClock, 1000);
        }
    };

    // Expose utilities globally
    window.ForooshyarAdmin = ForooshyarAdmin;
    window.PersianNumber = PersianNumber;
    window.JalaliCalendar = JalaliCalendar;
    window.RTLUtils = RTLUtils;
    window.AccessibilityEnhancements = AccessibilityEnhancements;

    // Auto-initialize
    ForooshyarAdmin.init();

})(jQuery);