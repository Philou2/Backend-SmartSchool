<?php

namespace App\Controller\School\Schooling\Discipline;

use App\Entity\School\Schooling\Discipline\Suspension;
use App\Repository\School\Schooling\Discipline\SuspensionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class GetSuspensionController extends AbstractController
{

    public function __invoke(Request $request, SuspensionRepository $suspensionRepository): JsonResponse
    {
        $suspensions = $suspensionRepository->findAll();

        $mySuspensions = [];
        foreach ($suspensions as $suspension){

            $mySuspensions [] = [
                '@id' => "/api/get/suspension/".$suspension->getId(),
                '@type' => 'Suspension',
                'id' => $suspension->getId(),
                'startDate' => $suspension->getStartDate() ? $suspension->getStartDate()->format('Y-m-d') : '',
                'endDate' => $suspension->getEndDate() ? $suspension->getEndDate()->format('Y-m-d') : '',
                'school' => $suspension->getSchool() ? $suspension->getSchool()->getName() : '',
                'class' => $suspension->getSchoolClass() ? $suspension->getSchoolClass()->getCode() : '',
                'sequence' => $suspension->getSequence() ? $suspension->getSequence()->getCode() : '',
                'motif' => $suspension->getMotif() ? $suspension->getMotif()->getName() : '',
                'observations' => $suspension->getObservations(),
                'studentRegistration' => $this->serializeStudentRegistration($suspension),
            ];
        }

        return $this->json(['hydra:member' => $mySuspensions]);

    }

    public function serializeStudentRegistration(Suspension $suspension): array
    {
        $studentRegistrations = $suspension->getStudentRegistration();
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