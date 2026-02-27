<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $companyRole = Role::where('name', 'company')->first();
        $individualRole = Role::where('name', 'individual')->first();

        // Random company and individual users
        $companies = User::factory()->count(5)->company()->create()
            ->each(fn ($u) => $u->assignRole($companyRole));

        $individuals = User::factory()->count(15)->individual()->create()
            ->each(fn ($u) => $u->assignRole($individualRole));

        // Fixed demo accounts
        $companyPassword = $this->generateValidPassword();
        $individualPassword = $this->generateValidPassword();

        // log generated values for developers
        $this->command->info('Demo company password: ' . $companyPassword);
        $this->command->info('Demo individual password: ' . $individualPassword);

        $demoCompany = User::firstOrCreate(
            ['idcode' => 'user_' . Str::uuid()->toString()],
            [
                'login_id' => 'brightpath_hr',
                'nickname' => 'BrightPath Recruiting',
                'email' => 'hiring@brightpathrecruiting.com',
                'password' => Hash::make($companyPassword),
                'user_type' => 'company',
            ]
        );
        $demoCompany->assignRole($companyRole);

        $demoIndividual = User::firstOrCreate(
            ['idcode' => 'user_' . Str::uuid()->toString()],
            [
                'login_id' => 'alex_morgan',
                'nickname' => 'Alex Morgan',
                'email' => 'alex.morgan@email.com',
                'password' => Hash::make($individualPassword),
                'user_type' => 'individual',
            ]
        );
        $demoIndividual->assignRole($individualRole);

        // Specific job postings for the demo company
        $demoJobData = [
            [
                'title' => 'Full Stack Developer',
                'requirement' => "3+ years of experience building web applications with modern frameworks (React, Vue, or Angular on the frontend; Laravel, Node.js, or Django on the backend).\n\nStrong proficiency in JavaScript/TypeScript and at least one server-side language. Experience with relational databases (PostgreSQL or MySQL) and RESTful API design.\n\nFamiliarity with Git, CI/CD pipelines, and cloud deployment (AWS or similar). Ability to work independently and communicate clearly in a remote-first environment.",
                'duty' => "Build and maintain features across the full stack — from database schema design to polished UI components. Collaborate with the product team to translate requirements into technical solutions.\n\nWrite clean, well-tested code and participate in code reviews. Identify and resolve performance issues and bugs across the application. Contribute to architectural decisions and help shape engineering best practices.",
                'salary_from' => 90000,
                'salary_to'   => 120000,
            ],
            [
                'title' => 'Talent Acquisition Specialist',
                'requirement' => "2+ years of experience in recruiting or talent acquisition, ideally within a staffing agency or high-growth company. Proven ability to manage full-cycle recruiting across multiple roles simultaneously.\n\nExperience sourcing candidates through LinkedIn Recruiter, job boards, and referral networks. Strong interpersonal skills and ability to build relationships with both candidates and hiring managers.\n\nFamiliarity with ATS platforms (Greenhouse, Lever, or similar) and data-driven recruiting metrics.",
                'duty' => "Own the end-to-end recruiting process for assigned roles — from job brief through offer acceptance. Source and engage passive candidates through proactive outreach and creative sourcing strategies.\n\nScreen applicants, coordinate interviews, and provide a positive candidate experience throughout the process. Partner with hiring managers to define role requirements and calibrate on candidate profiles. Track pipeline metrics and report on recruiting progress weekly.",
                'salary_from' => 65000,
                'salary_to'   => 85000,
            ],
            [
                'title' => 'HR Business Partner',
                'requirement' => "5+ years of HR experience with at least 2 years in a business partner or generalist role. Strong knowledge of employment law, performance management, and employee relations.\n\nDemonstrated ability to influence and advise managers at all levels. Experience supporting organizational change, workforce planning, and talent development initiatives.\n\nPHR or SHRM-CP certification preferred. Excellent communication, discretion, and problem-solving skills.",
                'duty' => "Serve as a trusted advisor to business leaders on all people-related matters including performance, compensation, and organizational design. Lead employee relations investigations and resolve workplace issues fairly and promptly.\n\nPartner with managers on performance improvement plans, promotions, and succession planning. Drive HR programs including engagement surveys, onboarding, and learning initiatives. Ensure compliance with employment laws and company policies across all locations.",
                'salary_from' => 85000,
                'salary_to'   => 110000,
            ],
            [
                'title' => 'Payroll & Benefits Administrator',
                'requirement' => "3+ years of experience processing payroll for a mid-size organization (200+ employees). Proficiency with payroll software such as ADP, Paychex, or Gusto.\n\nSolid understanding of federal and state payroll tax regulations, garnishments, and year-end reporting (W-2, 1099). Experience administering employee benefits programs including health, dental, 401(k), and leave policies.\n\nHigh attention to detail and ability to handle sensitive information with confidentiality.",
                'duty' => "Process bi-weekly and semi-monthly payroll accurately and on time for all employees. Administer employee benefits enrollments, changes, and terminations, and serve as the primary contact for benefits questions.\n\nReconcile payroll reports and resolve discrepancies with finance. Ensure compliance with federal, state, and local payroll regulations. Support open enrollment and coordinate with benefits brokers and carriers.",
                'salary_from' => 60000,
                'salary_to'   => 78000,
            ],
            [
                'title' => 'Recruitment Marketing Coordinator',
                'requirement' => "1–3 years of experience in marketing, employer branding, or HR communications. Strong writing skills with the ability to craft compelling job descriptions and social media content.\n\nFamiliarity with digital marketing tools, social media platforms (LinkedIn, Instagram, Indeed), and basic analytics. Experience with design tools such as Canva or Adobe Creative Suite is a plus.\n\nPassion for candidate experience and building an authentic employer brand.",
                'duty' => "Create and manage job postings across multiple platforms, ensuring consistent and compelling messaging. Develop employer brand content including social media posts, employee spotlights, and career page copy.\n\nTrack and analyze job board performance and candidate source data to optimize spend and strategy. Coordinate recruitment events, career fairs, and campus recruiting initiatives. Collaborate with the talent acquisition team to align marketing efforts with hiring priorities.",
                'salary_from' => 50000,
                'salary_to'   => 65000,
            ],
        ];

        $demoJobs = collect();
        foreach ($demoJobData as $data) {
            $demoJobs->push(JobPosting::firstOrCreate(
                [
                    'company_user_id' => $demoCompany->id,
                    'title' => $data['title'],
                ],
                [
                    'idcode' => 'job_' . Str::uuid()->toString(),
                    'requirement' => $data['requirement'],
                    'duty' => $data['duty'],
                    'salary_from' => $data['salary_from'],
                    'salary_to' => $data['salary_to'],
                ]
            ));
        }

        // Random job postings for other companies
        $otherJobs = collect();
        foreach ($companies as $company) {
            $jobs = JobPosting::factory()->count(rand(2, 5))->create(['company_user_id' => $company->id]);
            $otherJobs = $otherJobs->merge($jobs);
        }

        $allJobs = $demoJobs->merge($otherJobs);

        $allApplications = collect();

        // Random individuals apply to random jobs
        foreach ($individuals as $individual) {
            $jobCount = min(rand(2, 4), $allJobs->count());
            if ($jobCount > 0) {
                foreach ($allJobs->random($jobCount) as $job) {
                    $allApplications->push(Application::factory()->create([
                        'job_id' => $job->id,
                        'applicant_user_id' => $individual->id,
                    ]));
                }
            }
        }

        // Demo individual applies to all demo jobs
        foreach ($demoJobs as $job) {
            $app = Application::firstOrCreate(
                ['job_id' => $job->id, 'applicant_user_id' => $demoIndividual->id],
                Application::factory()->make([
                    'job_id' => $job->id,
                    'applicant_user_id' => $demoIndividual->id,
                ])->toArray()
            );
            $allApplications->push($app);
        }

        $this->command->info('Demo data seeded.');
        $this->command->info('');
        $this->command->info('Demo accounts:');
        $this->command->info('  Company:    brightpath_hr / ' . $companyPassword);
        $this->command->info('  Individual: alex_morgan / ' . $individualPassword);
        $this->command->info('');
        $this->command->info('Created:');
        $this->command->info('  - ' . ($companies->count() + 1) . ' company users');
        $this->command->info('  - ' . ($individuals->count() + 1) . ' individual users');
        $this->command->info('  - ' . $allJobs->count() . ' job postings');
        $this->command->info('  - ' . $allApplications->count() . ' applications');
    }

    private function generateValidPassword(): string
    {
        $upper = fake()->randomElement(range('A', 'Z'));
        $lower = fake()->randomElement(range('a', 'z'));
        $digit = fake()->randomElement(range('0', '9'));
        $special = fake()->randomElement(['@', '$', '!', '%', '*', '?', '&']);

        $remaining = \Illuminate\Support\Str::random(fake()->numberBetween(8, 16));

        $chars = str_split($upper . $lower . $digit . $special . $remaining);
        shuffle($chars);

        return implode('', $chars);
    }
}

