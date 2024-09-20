<?php
namespace App\State\Processor\Inventory\Delivery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Inventory\Delivery;
use App\Repository\Inventory\DeliveryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

final class CancelDeliveryProcessor implements ProcessorInterface
{

    public function __construct(private readonly ProcessorInterface $processor,
                                private readonly EntityManagerInterface $manager,
                                Private readonly DeliveryRepository $deliveryRepository) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {

        if(!$data instanceof Delivery)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'This data must be type of delivery.'], 404);
        }

        $delivery = $this->deliveryRepository->find($data->getId());

        if(!$delivery)
        {
            // Warning
            return new JsonResponse(['hydra:description' => 'This delivery is not found.'], 404);
        }

        $delivery->setStatus('draft');
        $this->manager->flush();

        return $this->processor->process($delivery, $operation, $uriVariables, $context);
    }
}
