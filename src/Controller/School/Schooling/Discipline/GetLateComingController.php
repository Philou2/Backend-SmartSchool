<?php

namespace App\Controller\School\Schooling\Discipline;

use App\Entity\School\Schooling\Discipline\LateComing;
use App\Repository\School\Schooling\Discipline\LateComingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class GetLateComingController extends AbstractController
{

    public function __invoke(Request $request, LateComingRepository $lateComingRepository): JsonResponse
    {
        $lateComings = $lateComingRepository->findAll();

        $myLateComings = [];
        foreach ($lateComings as $lateComing){

            $myLateComings [] = [
                '@id' => "/api/get/lateComing/".$lateComing->getId(),
                '@type' => 'LateComing',
                'id' => $lateComing->getId(),
                'startDate' => $lateComing->getStartDate() ? $lateComing->getStartDate()->format('Y-m-d') : '',
                'startTime' => $lateComing->getStartTime() ? $lateComing->getStartTime()->format('H:i') : '',
                'endTime' => $lateComing->getEndTime() ? $lateComing->getEndTime()->format('H:i') : '',
                'school' => $lateComing->getSchool() ? $lateComing->getSchool()->getName() : '',
                'class' => $lateComing->getSchoolClass() ? $lateComing->getSchoolClass()->getCode() : '',
                'sequence' => $lateComing->getSequence() ? $lateComing->getSequence()->getCode() : '',
                'motif' => $lateComing->getMotif() ? $lateComing->getMotif()->getName() : '',
                'observations' => $lateComing->getObservations(),
                'studentRegistration' => $this->serializeStudentRegistration($lateComing),
            ];
        }

        return $this->json(['hydra:member' => $myLateComings]);

    }

    public function serializeStudentRegistration(LateComing $lateComing): array
    {
        $studentRegistrations = $lateComing->getStudentRegistration();
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