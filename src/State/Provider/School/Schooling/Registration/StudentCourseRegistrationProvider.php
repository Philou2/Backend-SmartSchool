<?php

namespace App\State\Provider\School\Schooling\Registration;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\School\Schooling\Registration\StudentCourseRegistrationRepository;
use App\Repository\Security\Institution\InstitutionRepository;
use Symfony\Component\HttpFoundation\Request;

class StudentCourseRegistrationProvider implements ProviderInterface
{

    public function __construct(
        private StudentCourseRegistrationRepository $studCourseRegRepository,
        private InstitutionRepository               $institutionRepository
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Retrieve the state from somewhere
        $studentMatricule = $uriVariables['matricule'] ;
        $institution = $this->institutionRepository->find(1);
        return $this->studCourseRegRepository->findByStudentMatricule($institution,$studentMatricule);
    }
}
