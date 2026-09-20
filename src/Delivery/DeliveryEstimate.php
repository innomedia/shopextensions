<?php

namespace ShopExtensions\Delivery;

use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\i18n\i18n;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Expected delivery date, e.g. for a product page ("Lieferung voraussichtlich am Dienstag, 22. September").
 * Opt-in: needs the SiteConfig fields of {@see SiteConfigDeliveryExtension} (see
 * _config/delivery.yml.example); the class itself can also be built with explicit values.
 *
 *   ship date     = today + DispatchLeadDays business days (0 = today, if today is a business day)
 *   delivery date = ship date + DeliveryDays business days
 *
 * Business days: every weekday not in `non_business_days` (default Saturday/Sunday). Public holidays
 * come from the hook updateIsBusinessDay(&$isBusinessDay, $date) (no holiday calendar is shipped) and
 * there is no order cut-off time.
 *
 * Dev only: `?deliverytoday=2026-09-18` (config dev_today_param) overrides "today" for testing.
 * Template: `<% include ShopExtensions/Includes/DeliveryEstimate %>` with $DeliveryEstimate in scope.
 */
class DeliveryEstimate
{
    use Injectable;
    use Configurable;
    use Extensible;

    /** @config */
    private static string $timezone = 'Europe/Berlin';

    /** @config ISO-8601 weekday numbers (1 = Monday … 7 = Sunday) without dispatch/delivery */
    private static array $non_business_days = [6, 7];

    /** @config ICU pattern of getDeliveryDateNice(): "Dienstag, 22. September" */
    private static string $date_pattern = 'EEEE, d. MMMM';

    /** @config Locale for the date format; empty = current i18n locale */
    private static string $locale = '';

    /** @config GET parameter that overrides "today" in dev mode; empty = off */
    private static string $dev_today_param = 'deliverytoday';

    public function __construct(
        private ?DateTimeImmutable $today = null,
        private ?int $dispatchLeadDays = null,
        private ?int $deliveryDays = null,
    ) {
    }

    /**
     * Estimate from SiteConfig.DispatchLeadDays / DeliveryDays, honouring the dev-only override.
     */
    public static function fromSiteConfig(?SiteConfig $config = null): static
    {
        $config ??= SiteConfig::current_site_config();
        $today = null;
        $param = (string) static::config()->get('dev_today_param');
        $controller = Controller::curr();
        if ($param !== '' && Director::isDev() && $controller) {
            $request = $controller->getRequest();
            $override = $request ? (string) $request->getVar($param) : '';
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $override)) {
                $today = new DateTimeImmutable($override, new DateTimeZone(static::config()->get('timezone')));
            }
        }

        return static::create($today, (int) $config->DispatchLeadDays, (int) $config->DeliveryDays);
    }

    public function getToday(): DateTimeImmutable
    {
        return ($this->today ?? new DateTimeImmutable('now', new DateTimeZone(static::config()->get('timezone'))))
            ->setTime(0, 0);
    }

    public function getShipDate(): DateTimeImmutable
    {
        $date = $this->getToday();
        // lead time 0 on a non-business day → first business day
        while (!$this->isBusinessDay($date)) {
            $date = $date->modify('+1 day');
        }

        return $this->addBusinessDays($date, max(0, (int) $this->dispatchLeadDays));
    }

    public function getDeliveryDate(): DateTimeImmutable
    {
        return $this->addBusinessDays($this->getShipDate(), max(0, (int) $this->deliveryDays));
    }

    /** "Dienstag, 22. September" */
    public function getDeliveryDateNice(): string
    {
        return $this->format($this->getDeliveryDate());
    }

    /** "2026-09-22" for <time datetime> */
    public function getDeliveryDateISO(): string
    {
        return $this->getDeliveryDate()->format('Y-m-d');
    }

    public function format(DateTimeImmutable $date): string
    {
        $locale = (string) static::config()->get('locale') ?: i18n::get_locale();
        $formatter = new IntlDateFormatter(
            $locale,
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            static::config()->get('timezone'),
            IntlDateFormatter::GREGORIAN,
            static::config()->get('date_pattern')
        );

        return (string) $formatter->format($date);
    }

    public function isBusinessDay(DateTimeImmutable $date): bool
    {
        $isBusinessDay = !in_array((int) $date->format('N'), array_map('intval', (array) static::config()->get('non_business_days')), true);
        $this->extend('updateIsBusinessDay', $isBusinessDay, $date);

        return $isBusinessDay;
    }

    private function addBusinessDays(DateTimeImmutable $date, int $days): DateTimeImmutable
    {
        while ($days > 0) {
            $date = $date->modify('+1 day');
            if ($this->isBusinessDay($date)) {
                $days--;
            }
        }

        return $date;
    }
}
