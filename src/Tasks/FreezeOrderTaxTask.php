<?php

namespace ShopExtensions\Tasks;

use SilverShop\Model\Order;
use SilverStripe\Dev\BuildTask;
use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\PolyExecution\PolyOutput;

/**
 * One-off migration: freeze the per-line VAT (OrderItem.TaxRate/TaxAmount) and tax mode
 * (Order.TaxMode) onto already-placed orders.
 *
 * Orders placed before these fields existed have no frozen tax, so their tax display would still
 * be recalculated from the current config. This task runs the same freeze that placement now does
 * ({@see \ShopExtensions\OrderExtension::freezeItemTaxes()}) against the current calculation. Cart
 * orders are skipped (they recalculate by design).
 *
 * Safe to re-run; it simply re-freezes from the current calculation.
 */
class FreezeOrderTaxTask extends BuildTask
{
    protected string $title = 'Freeze per-line tax on placed orders';

    protected static string $description = 'Backfill OrderItem.TaxRate/TaxAmount + Order.TaxMode on placed orders.';

    private static $segment = 'freeze-order-tax';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $done = 0;

        foreach (Order::get()->exclude('Status', 'Cart') as $order) {
            if (!$order->hasMethod('freezeItemTaxes')) {
                continue;
            }
            $order->freezeItemTaxes();
            $order->write();
            $done++;

            $lines = [];
            foreach ($order->Items() as $item) {
                $lines[] = sprintf('%s%%=%s', rtrim(rtrim((string) $item->TaxRate, '0'), '.'), $item->TaxAmount);
            }
            $output->writeln(sprintf(
                'Order #%s (%s) [%s]: %s',
                $order->ID,
                $order->Reference,
                $order->TaxMode,
                implode(', ', $lines)
            ));
        }

        $output->writeln(sprintf('Done. Frozen: %d.', $done));
        return 0;
    }
}
