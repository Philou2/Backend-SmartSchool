<?php

namespace App\Controller\School\Schooling\Discipline;

use App\Entity\School\Schooling\Discipline\SummonParent;
use App\Repository\School\Schooling\Discipline\SummonParentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class GetSummonParentsController extends AbstractController
{

    public function __invoke(Request $request, SummonParentRepository $summonParentsRepository): JsonResponse
    {
        $summonParents = $summonParentsRepository->findAll();

        $mySummonParents = [];
        foreach ($summonParents as $summonParent){

            $mySummonParents [] = [
                '@id' => "/api/get/summonParent/".$summonParent->getId(),
                '@type' => 'SummonParent',
                'id' => $summonParent->getId(),
                'startDate' => $summonParent->getStartDate() ? $summonParent->getStartDate()->format('Y-m-d') : '',
                'startTime' => $summonParent->getStartTime() ? $summonParent->getStartTime()->format('H:i') : '',
                'endTime' => $summonParent->getEndTime() ? $summonParent->getEndTime()->format('H:i') : '',
                'school' => $summonParent->getSchool() ? $summonParent->getSchool()->getName() : '',
                'class' => $summonParent->getSchoolClass() ? $summonParent->getSchoolClass()->getCode() : '',
                'sequence' => $summonParent->getSequence() ? $summonParent->getSequence()->getCode() : '',
                'motif' => $summonParent->getReason() ? $summonParent->getReason()->getName() : '',
                'observations' => $summonParent->getObservations(),
                'studentRegistration' => $this->serializeStudentRegistration($summonParent),
            ];
        }

        return $this->json(['hydra:member' => $mySummonParents]);

    }

    public function serializeStudentRegistration(SummonParent $summonParent): array
    {
        $studentRegistrations = $summonParent->getStudentRegistration();
        $myStudentRegistrations = [];
        foreach ($studentRegistrations as $studentRegistration){
            $myStudentRegistrations[] = [
                '@type' => 'StudentRegistration',
                '@id' => '/api/get/student-registration/'.$studentRegistration->getId(),
                'id' => $studentRegistration->getId(),
                'name' => $studentRegistration->getStudent()->getName(),
                'firstName' => $studentRegistration->getStudent()->getFirstName(),
            ];
        }
        return $myStudentRegistrations;
    }

}