<?php

namespace App\Controller\Billing\Purchase;

use App\Entity\Billing\Sale\SaleReturnInvoiceItem;
use App\Entity\Security\User;
use App\Repository\Billing\Sale\SaleReturnInvoiceItemRepository;
use App\Repository\Billing\Sale\SaleReturnInvoiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class GetPurchaseReturnInvoiceSumItemAmountController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage,
                                )
    {
    }

    public function __invoke(SaleReturnInvoiceItemRepository $saleReturnInvoiceItemRepository,
                             SaleReturnInvoiceRepository $saleReturnInvoiceRepository,
                             Request $request): JsonResponse
    {

        $id = $request->get('id');
        $returnInvoice = $saleReturnInvoiceRepository->find($id);
        if (!$returnInvoice){

            return new JsonResponse(['hydra:description' => 'This invoice is not found.'], 404);
        }

        $taxResult = 0;
        $discountAmount = 0;
        $saleReturnInvoiceItems = $saleReturnInvoiceItemRepository->findBy(['saleReturnInvoice' => $returnInvoice]);

        foreach ($saleReturnInvoiceItems as $saleReturnInvoiceItem){
            if ($saleReturnInvoiceItem->getTaxes()){
                foreach ($saleReturnInvoiceItem->getTaxes() as $tax){
                    $taxResult = $taxResult + $saleReturnInvoiceItem->getAmount() * $tax->getRate() / 100;
                }
            }

            //
            //$amountWithTax = $taxResult
            $discountAmount = $discountAmount + $saleReturnInvoiceItem->getAmount() * $saleReturnInvoiceItem->getDiscount() / 100;
        }

        $amountTtc = $saleReturnInvoiceItemRepository->sumAmountByInvoice($returnInvoice)[0][1] + $taxResult - $discountAmount;

        $items = [
            'totalHt' => number_format($saleReturnInvoiceItemRepository->sumAmountByInvoice($returnInvoice)[0][1], 2, ',',' '),
            'taxes'   => number_format($taxResult, 2, ',',' '),
            'discountAmount' => number_format($discountAmount, 2, ',',' '),
            'totalTtc' => number_format($amountTtc, 2, ',',' '),
        ];



        return $this->json(['hydra:member' => $items]);
    }


    public function getUser(): ?User
    {
        $token = $this->tokenStorage->getToken();

        if (!$token) {
            return null;
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return null;
        }

        return $user;
    }

    public function taxes(SaleReturnInvoiceItem $saleReturnInvoiceItem){
        $taxes = [];

        foreach ($saleReturnInvoiceItem->getTaxes() as $tax){
            $taxes[] = [
                'id' => $tax->getId(),
                'name' => $tax->getName(),
                'rate' => $tax->getRate(),
                'label' => $tax->getLabel(),
            ];
        }
        return $taxes;
    }
}
