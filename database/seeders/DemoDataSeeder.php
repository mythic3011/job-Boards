<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Get roles
        $companyRole = Role::where('name', 'company')->first();
        $individualRole = Role::where('name', 'individual')->first();

        // Create demo company users
        $companies = User::factory()
            ->count(5)
            ->company()
            ->create()
            ->each(function ($user) use ($companyRole) {
                $user->assignRole($companyRole);
            });

        // Create demo individual users
        $individuals = User::factory()
            ->count(15)
            ->individual()
            ->create()
            ->each(function ($user) use ($individualRole) {
                $user->assignRole($individualRole);
            });

        // Create specific demo accounts for easy testing
        $demoCompany = User::create([
            'idcode' => 'user_demo_company',
            'login_id' => 'demo_company',
            'nickname' => 'Demo Company Inc.',
            'email' => 'company@demo.com',
            'password' => Hash::make('password'),
            'user_type' => 'company',
        ]);
        $demoCompany->assignRole($companyRole);

        $demoIndividual = User::create([
            'idcode' => 'user_demo_individual',
            'login_id' => 'demo_user',
            'nickname' => 'Demo User',
            'email' => 'user@demo.com',
            'password' => Hash::make('password'),
            'user_type' => 'individual',
        ]);
        $demoIndividual->assignRole($individualRole);

        // Create job postings for demo company
        $demoJobs = JobPosting::factory()
            ->count(3)
            ->create([
                'company_user_id' => $demoCompany->id,
            ]);

        // Create job postings for other companies
        $otherJobs = collect();
        foreach ($companies as $company) {
            $jobs = JobPosting::factory()
                ->count(rand(2, 5))
                ->create([
                    'company_user_id' => $company->id,
                ]);
            $otherJobs = $otherJobs->merge($jobs);
        }

        $allJobs = $demoJobs->merge($otherJobs);

        // Create applications from individuals
        foreach ($individuals as $individual) {
            // Each individual applies to 2-4 random jobs
            $jobCount = min(rand(2, 4), $allJobs->count());
            if ($jobCount > 0) {
                $jobsToApply = $allJobs->random($jobCount);
                
                foreach ($jobsToApply as $job) {
                    Application::factory()->create([
                        'job_id' => $job->id,
                        'applicant_user_id' => $individual->id,
                    ]);
                }
            }
        }

        // Demo individual applies to demo jobs
        foreach ($demoJobs as $job) {
            Application::factory()->create([
                'job_id' => $job->id,
                'applicant_user_id' => $demoIndividual->id,
            ]);
        }

        $this->command->info('Demo data created successfully!');
        $this->command->info('');
        $this->command->info('Demo Accounts:');
        $this->command->info('  Company:  login_id="demo_company" or email="company@demo.com", password="password"');
        $this->command->info('  Individual: login_id="demo_user" or email="user@demo.com", password="password"');
        $this->command->info('');
        $this->command->info('Created:');
        $this->command->info('  - ' . User::where('user_type', 'company')->count() . ' company users');
        $this->command->info('  - ' . User::where('user_type', 'individual')->count() . ' individual users');
        $this->command->info('  - ' . JobPosting::count() . ' job postings');
        $this->command->info('  - ' . Application::count() . ' applications');
    }
}
