<?php

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

require_once __DIR__ . '/../Order/Sync.php';

class Shopware_Components_Blisstribute_Command_OrderExport extends ShopwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('bliss:orderExport')
            ->setDescription('Exports a single order to blisstribute.')
            ->addArgument(
                'orderNumber',
                InputArgument::REQUIRED,
                'The order number to export.'
            )
            ->addArgument(
                'force',
                InputArgument::OPTIONAL,
                'Force the export even if status says its already done.',
                false
            )
            ->setHelp(<<<EOF
The <info>%command.name%</info> exports a single order to blisstribute.
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $orderNumber = $input->getArgument('orderNumber');
        $force = (bool)$input->getArgument('force');

        $output->writeln('<info>blisstribute order export started for order number ' . $orderNumber . '</info>');

        $modelManager = $this->container->get('models');
        $orderRepository = $modelManager->getRepository('Shopware\Models\Order\Order');

        /** @var \Shopware\Models\Order\Order $order */
        $order = $orderRepository->findOneBy(array('number' => $orderNumber));
        if ($order === null) {
            $output->writeln('<error>buuuhuuhuuu.. could not load order. script terminated.</error>');
            return null;
        }

        /** @var \Shopware\CustomModels\Blisstribute\BlisstributeOrderRepository $blisstributeOrderRepository */
        $blisstributeOrderRepository = $modelManager->getRepository('Shopware\CustomModels\Blisstribute\BlisstributeOrder');
        $blisstributeOrder = $blisstributeOrderRepository->findByOrder($order);
        if ($blisstributeOrder == null) {
            $output->writeln('<error>buuuhuuhuuu.. could not load blisstribute order. script terminated.</error>');
            return;
        }

        $orderSync = new Shopware_Components_Blisstribute_Order_Sync(
            $this->container->get('plugins')->Backend()->ExitBBlisstribute()->Config()
        );
        $result = $orderSync->processSingleOrderSync($blisstributeOrder, $force);

        $output->writeln('<info>export result: ' . (int)$result . '</info>');
    }
}