<?php

namespace Database\Factories;

use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApplicationFactory extends Factory
{
    private static array $messages = [
        "I'm excited to apply for this position. With over four years of experience in a similar role, I've developed strong skills that I believe align well with what you're looking for. I'm a quick learner, work well in team environments, and am passionate about delivering quality results. I'd love the opportunity to bring my experience to your organization.",
        "Please find my application for this role. I have a proven track record in this field and am confident I can make a meaningful contribution to your team. I'm particularly drawn to this opportunity because of the scope of responsibilities and the chance to grow professionally. I look forward to discussing my background in more detail.",
        "I am writing to express my strong interest in this position. My background includes hands-on experience with the core responsibilities outlined in the job description, and I've consistently received positive feedback from managers and colleagues for my work ethic and attention to detail. I'm eager to bring that same dedication to your team.",
        "This role caught my attention immediately — it's a strong match for my skills and career goals. I have direct experience with the key requirements listed and have successfully handled similar responsibilities in my previous positions. I'm motivated, reliable, and ready to hit the ground running.",
        "I'd like to be considered for this opportunity. Over the past several years I've built expertise that maps closely to what you're seeking. I thrive in fast-paced environments, enjoy solving complex problems, and take pride in producing high-quality work. I'm confident I'd be a strong addition to your team.",
        "Thank you for considering my application. I bring a combination of relevant experience and a genuine enthusiasm for this type of work. I've spent the last few years honing my skills in this area and am looking for a role where I can continue to grow while making a real impact. I believe this position is that opportunity.",
        "I'm applying for this role because it aligns closely with both my experience and my professional interests. I have a solid foundation in the core areas described and have taken on increasing responsibility throughout my career. I'm a collaborative team player who also works well independently, and I'm excited about the possibility of joining your organization.",
        "I was drawn to this posting because of the clear focus on quality and results — values I share. My background has prepared me well for the responsibilities described, and I've consistently delivered strong outcomes in similar roles. I'm looking for a team where I can contribute meaningfully and continue to develop, and I believe this could be that place.",
        null,
        null,
    ];

    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'idcode' => 'app_' . Str::uuid()->toString(),
            'job_id' => JobPosting::factory(),
            'applicant_user_id' => User::factory()->individual(),
            'message' => fake()->randomElement(self::$messages),
            'cv_file_path' => 'cvs/' . Str::uuid()->toString() . '.pdf',
            'cv_original_name' => fake()->randomElement([
                "{$firstName}_{$lastName}_Resume.pdf",
                "{$firstName}_{$lastName}_CV.pdf",
                "Resume_{$lastName}.pdf",
                "CV_{$firstName}_{$lastName}_2025.pdf",
                'resume.pdf',
                'cv.pdf',
            ]),
            'cv_mime' => 'application/pdf',
            'cv_size_bytes' => fake()->numberBetween(50000, 4500000),
            'cv_sha256' => bin2hex(random_bytes(32)),
        ];
    }
}
