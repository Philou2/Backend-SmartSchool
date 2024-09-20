<?php

namespace App\Controller\Billing\Sale;

use App\Entity\Security\User;
use App\Repository\Inventory\StockRepository;
use App\Repository\Product\ItemCategoryRepository;
use App\Repository\Product\ItemRepository;
use App\Repository\Product\ItemTypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsController]
class GetItemTypeCategoryController extends AbstractController
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage)
    {
    }


    #[Route('api/get/change/item/type/{type}', name: 'get_change_item_type')]
    #[Route('api/get/change/item/category/{category}', name: 'get_change_item_category')]
    #[Route('api/get/change/item/type/{type}/category/{category}', name: 'get_change_item_type_category')]
    public function __invoke(ItemRepository $itemRepository, ItemTypeRepository $itemTypeRepository, ItemCategoryRepository $itemCategoryRepository, StockRepository $stockRepository, Request $request, $type = null, $category = null): JsonResponse
    {
        $routeName = $request->attributes->get('_route');


        if ($routeName == 'get_change_item_type'){
            $typeObject = $itemTypeRepository->find($type);

            $items = $itemRepository->findBy(['itemType' => $typeObject]);
        }

        elseif ($routeName == 'get_change_item_category'){
            $categoryObject = $itemCategoryRepository->find($category);

            $items = $itemRepository->findBy(['itemCategory' => $categoryObject]);
        }

        elseif ($routeName == 'get_change_item_type_category')
        {
            $typeObject = $itemTypeRepository->find($type);
            $categoryObject = $itemCategoryRepository->find($category);

            $items = $itemRepository->findBy(['itemType' => $typeObject, 'itemCategory' => $categoryObject]);
        }

        else {
            return new JsonResponse(['hydra:description' => 'Query cannot continue parameters are mendatory.'], 404);
        }

        $all = [];

        foreach ($items as $item){

            // VERFICATION STOCK: get les items dont la quantite est superieur a 0./ Possibilite de le faire dans le repo mais c'est long...😒
            $stocks = $stockRepository->findItemWhereStock($item, $this->getUser()->getBranch());
            foreach ($stocks as $stock){
                $all[] = [
                    // 'id' => $stock->getItem()->getId(),
                    // '@id' => '/api/get/item/'. $item->getId(),
                    'id' => $stock->getId(),
                    '@id' => '/api/get/stock/'. $stock->getId(),

                    // 'name' => $stock->getItem()->getName(),
                    'name' => $stock->getItem()->getName(). ' [ '. $stock->getAvailableQte() . ' - ' . $stock->getReference() .' ]',
                    'barcode' => $stock->getItem()->getBarcode(),
                    'position' => $stock->getItem()->getPosition(),
                    'price' => $stock->getItem()->getPrice(),
                    'itemType' => $stock->getItem()->getItemType(),
                    'itemCategory' => $stock->getItem()->getItemCategory(),
                    'reference' => $stock->getItem()->getReference(),
                    'batchNumber' => $stock->getItem()->getBatchNumber(),

                ];
            }

        }

        return $this->json(['hydra:member' => $all]);
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
}
