<?php

namespace Database\Factories;

use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class JobPostingFactory extends Factory
{
    private static array $jobs = [
        [
            'title' => 'Senior Software Engineer',
            'requirement' => "Bachelor's degree in Computer Science, Software Engineering, or a related field, or equivalent practical experience.\n\n5+ years of professional software development experience with strong proficiency in one or more of the following: Python, Java, Go, or TypeScript.\n\nSolid understanding of data structures, algorithms, and system design principles. Experience designing and building scalable distributed systems.\n\nFamiliarity with cloud platforms (AWS, GCP, or Azure) and containerization tools such as Docker and Kubernetes. Strong communication skills and ability to work effectively in a collaborative team environment.",
            'duty' => "Design, develop, and maintain high-quality software systems that serve millions of users. Collaborate with product managers, designers, and other engineers to define requirements and deliver features on schedule.\n\nConduct thorough code reviews and mentor junior engineers to uphold engineering standards. Identify performance bottlenecks and implement optimizations across the stack. Participate in on-call rotations and contribute to incident response and post-mortem processes.",
            'salary_from' => 110000,
            'salary_to'   => 145000,
        ],
        [
            'title' => 'Product Manager',
            'requirement' => "3+ years of product management experience, preferably in a SaaS or technology company. Demonstrated ability to define product vision, build roadmaps, and drive execution across cross-functional teams.\n\nStrong analytical skills with experience using data to inform product decisions. Familiarity with agile development methodologies and tools such as Jira or Linear.\n\nExcellent written and verbal communication skills. Ability to influence without authority and align stakeholders at all levels of the organization.",
            'duty' => "Own the product roadmap for one or more product areas, from ideation through launch and iteration. Work closely with engineering, design, sales, and customer success to deliver products that solve real user problems.\n\nDefine success metrics and track product performance post-launch. Gather and synthesize customer feedback, market research, and competitive intelligence to inform prioritization. Present product updates and strategy to leadership and company-wide audiences.",
            'salary_from' => 95000,
            'salary_to'   => 130000,
        ],
        [
            'title' => 'UX/UI Designer',
            'requirement' => "3+ years of experience in UX/UI design for web or mobile products. Proficiency in Figma or similar design tools. Strong portfolio demonstrating end-to-end design process from research to high-fidelity prototypes.\n\nExperience conducting user research, usability testing, and translating insights into design decisions. Solid understanding of accessibility standards (WCAG 2.1) and responsive design principles.\n\nAbility to communicate design rationale clearly to both technical and non-technical stakeholders.",
            'duty' => "Lead the design of new features and improvements across web and mobile platforms. Conduct user interviews, surveys, and usability tests to validate design decisions and uncover pain points.\n\nCreate wireframes, prototypes, and high-fidelity mockups that clearly communicate design intent. Collaborate closely with engineers to ensure accurate implementation of designs. Maintain and evolve the design system to ensure consistency across the product.",
            'salary_from' => 85000,
            'salary_to'   => 115000,
        ],
        [
            'title' => 'Data Analyst',
            'requirement' => "Bachelor's degree in Statistics, Mathematics, Economics, Computer Science, or a related field. 2+ years of experience in a data analyst or business intelligence role.\n\nProficiency in SQL and at least one data visualization tool (Tableau, Looker, Power BI, or similar). Experience with Python or R for data analysis and scripting is a plus.\n\nStrong attention to detail and ability to translate complex data into clear, actionable insights for non-technical audiences.",
            'duty' => "Analyze large datasets to identify trends, patterns, and opportunities that drive business decisions. Build and maintain dashboards and reports that provide visibility into key business metrics.\n\nPartner with teams across the organization to define measurement frameworks and answer strategic questions with data. Ensure data quality and integrity by auditing pipelines and flagging anomalies. Present findings and recommendations to stakeholders in a clear and compelling way.",
            'salary_from' => 75000,
            'salary_to'   => 100000,
        ],
        [
            'title' => 'DevOps Engineer',
            'requirement' => "3+ years of experience in a DevOps, Site Reliability Engineering, or infrastructure role. Strong hands-on experience with cloud infrastructure on AWS, GCP, or Azure.\n\nProficiency with infrastructure-as-code tools such as Terraform or Pulumi. Experience with CI/CD pipelines (GitHub Actions, GitLab CI, Jenkins, or similar) and container orchestration using Kubernetes.\n\nSolid understanding of networking, security best practices, and monitoring/observability tooling (Datadog, Prometheus, Grafana, etc.).",
            'duty' => "Design, build, and maintain scalable and reliable cloud infrastructure supporting production services. Develop and improve CI/CD pipelines to enable fast, safe software delivery.\n\nMonitor system health, respond to incidents, and lead post-mortem reviews to prevent recurrence. Collaborate with engineering teams to embed security and reliability best practices into the development lifecycle. Evaluate and adopt new tools and technologies to improve operational efficiency.",
            'salary_from' => 100000,
            'salary_to'   => 135000,
        ],
        [
            'title' => 'Marketing Manager',
            'requirement' => "5+ years of experience in B2B or B2C marketing, with at least 2 years in a management or lead role. Proven track record of planning and executing multi-channel marketing campaigns that drive measurable results.\n\nStrong understanding of digital marketing channels including SEO, SEM, email, social media, and content marketing. Experience with marketing automation platforms (HubSpot, Marketo, or similar) and CRM tools.\n\nExcellent project management skills and ability to manage multiple campaigns simultaneously in a fast-paced environment.",
            'duty' => "Develop and execute integrated marketing campaigns aligned with company growth objectives. Manage a team of marketing specialists and coordinate with external agencies and freelancers.\n\nOversee content strategy, brand messaging, and go-to-market planning for new product launches. Track and report on campaign performance, optimizing spend and tactics based on data. Collaborate with sales to ensure alignment on messaging, lead quality, and pipeline goals.",
            'salary_from' => 85000,
            'salary_to'   => 115000,
        ],
        [
            'title' => 'Customer Success Manager',
            'requirement' => "2+ years of experience in customer success, account management, or a client-facing role, ideally in a SaaS environment. Strong interpersonal and communication skills with a genuine passion for helping customers achieve their goals.\n\nAbility to manage a portfolio of accounts, prioritize effectively, and identify expansion and renewal opportunities. Familiarity with CRM and customer success platforms (Salesforce, Gainsight, Intercom, or similar).\n\nComfort with data — able to analyze usage metrics and health scores to proactively identify at-risk accounts.",
            'duty' => "Serve as the primary point of contact for a portfolio of customers, building strong relationships and ensuring they realize value from the product. Conduct regular check-ins, business reviews, and onboarding sessions to drive adoption and satisfaction.\n\nMonitor customer health metrics and proactively address risks to retention. Identify upsell and expansion opportunities and collaborate with the sales team to close them. Advocate for customer needs internally by sharing feedback with product and engineering teams.",
            'salary_from' => 70000,
            'salary_to'   => 95000,
        ],
        [
            'title' => 'Financial Analyst',
            'requirement' => "Bachelor's degree in Finance, Accounting, Economics, or a related field. 2–4 years of experience in financial analysis, FP&A, investment banking, or a similar role.\n\nAdvanced proficiency in Excel and financial modeling. Experience with ERP systems (SAP, Oracle, NetSuite) and BI tools is a plus.\n\nStrong analytical mindset with the ability to synthesize complex financial data into clear recommendations. CFA or CPA designation (or progress toward one) is an advantage.",
            'duty' => "Prepare and maintain financial models, forecasts, and budgets to support strategic planning and decision-making. Analyze monthly financial results, identify variances, and provide commentary to senior leadership.\n\nSupport the annual budgeting process and quarterly reforecasting cycles. Conduct ad hoc financial analyses to evaluate business opportunities, cost reduction initiatives, and capital allocation decisions. Collaborate with accounting, operations, and business unit leaders to ensure financial accuracy and alignment.",
            'salary_from' => 80000,
            'salary_to'   => 105000,
        ],
        [
            'title' => 'Registered Nurse',
            'requirement' => "Current and unrestricted RN license in the state of practice. Associate's or Bachelor's degree in Nursing (BSN preferred). BLS and ACLS certifications required.\n\n2+ years of clinical nursing experience, preferably in an acute care or specialty setting. Strong clinical assessment skills and ability to prioritize care in a fast-paced environment.\n\nExcellent communication and teamwork skills. Commitment to patient-centered care and adherence to evidence-based practice standards.",
            'duty' => "Assess, plan, implement, and evaluate nursing care for assigned patients in accordance with established protocols and standards of practice. Administer medications and treatments accurately and monitor patients for adverse reactions.\n\nCollaborate with physicians, specialists, and interdisciplinary team members to coordinate comprehensive patient care. Educate patients and families on diagnoses, treatment plans, and discharge instructions. Maintain accurate and timely documentation in the electronic health record (EHR).",
            'salary_from' => 72000,
            'salary_to'   => 95000,
        ],
        [
            'title' => 'Warehouse Associate',
            'requirement' => "High school diploma or equivalent. Prior experience in a warehouse, distribution center, or logistics environment preferred but not required — we provide on-the-job training.\n\nAbility to lift and move packages up to 50 lbs repeatedly throughout a shift. Comfortable working in a fast-paced environment and meeting productivity targets.\n\nBasic computer skills for scanning and inventory systems. Reliable attendance and strong attention to detail.",
            'duty' => "Receive, inspect, and process incoming shipments, verifying accuracy against purchase orders. Pick, pack, and prepare outbound orders for shipment according to established procedures.\n\nOperate warehouse equipment such as pallet jacks, forklifts (certification required), and barcode scanners. Maintain a clean, organized, and safe work environment in compliance with safety regulations. Assist with cycle counts and annual physical inventory.",
            'salary_from' => 36000,
            'salary_to'   => 45000,
        ],
        [
            'title' => 'Crane and Tower Operator',
            'requirement' => "Valid crane operator certification (NCCCO or equivalent) required. Minimum 3 years of experience operating cranes or tower equipment on commercial or industrial construction sites.\n\nAbility to read and interpret load charts, rigging plans, and site drawings. Strong spatial awareness and hand-eye coordination. Commitment to safety protocols and willingness to complete ongoing safety training.\n\nValid driver's license and ability to pass a pre-employment drug screen and background check.",
            'duty' => "Operate cranes and tower equipment to lift, move, and position materials and structural components on active construction sites. Conduct pre-shift inspections of equipment and report any defects or maintenance needs to the site supervisor.\n\nCoordinate with riggers, signal persons, and site foremen to execute lifts safely and efficiently. Maintain accurate daily logs of equipment usage, load weights, and operational conditions. Adhere strictly to OSHA regulations and company safety policies at all times.",
            'salary_from' => 58000,
            'salary_to'   => 82000,
        ],
        [
            'title' => 'Graphic Designer',
            'requirement' => "2+ years of professional graphic design experience. Proficiency in Adobe Creative Suite (Illustrator, Photoshop, InDesign). Strong portfolio showcasing a range of work including print, digital, and brand identity projects.\n\nSolid understanding of typography, color theory, and layout principles. Ability to manage multiple projects simultaneously and meet deadlines in a fast-paced environment.\n\nExperience with motion graphics or video editing (After Effects, Premiere) is a plus.",
            'duty' => "Concept and produce visual assets for marketing campaigns, social media, website, email, and print materials. Collaborate with the marketing and content teams to translate briefs into compelling visual designs.\n\nMaintain brand consistency across all design outputs and help evolve the visual identity as the brand grows. Prepare files for print production and digital publishing, ensuring technical specifications are met. Incorporate feedback from stakeholders and iterate designs efficiently.",
            'salary_from' => 55000,
            'salary_to'   => 75000,
        ],
        [
            'title' => 'Sales Representative',
            'requirement' => "1–3 years of experience in B2B or B2C sales. Proven ability to meet or exceed sales quotas. Strong prospecting, negotiation, and closing skills.\n\nExcellent verbal and written communication skills. Comfortable making outbound calls and conducting product demonstrations via video conference or in person.\n\nExperience with CRM software (Salesforce, HubSpot, or similar). Self-motivated with a competitive drive and a positive attitude.",
            'duty' => "Prospect and qualify new leads through outbound outreach, referrals, and inbound inquiries. Conduct discovery calls and product demonstrations to understand customer needs and present tailored solutions.\n\nManage the full sales cycle from initial contact through contract negotiation and close. Maintain accurate records of all sales activities and pipeline status in the CRM. Collaborate with customer success and marketing teams to ensure a smooth handoff and contribute to retention efforts.",
            'salary_from' => 50000,
            'salary_to'   => 70000,
        ],
        [
            'title' => 'Administrative Assistant',
            'requirement' => "2+ years of administrative or office support experience. Proficiency in Microsoft Office Suite (Word, Excel, Outlook, PowerPoint) and Google Workspace.\n\nExceptional organizational skills and attention to detail. Ability to manage competing priorities and maintain composure under pressure.\n\nStrong written and verbal communication skills. Discretion in handling confidential information. Experience with scheduling tools and travel booking platforms is a plus.",
            'duty' => "Provide administrative support to senior leadership and department teams, including calendar management, meeting coordination, and travel arrangements.\n\nPrepare correspondence, reports, presentations, and other documents as requested. Manage office supplies inventory and coordinate with vendors for office maintenance and services. Serve as the first point of contact for visitors and incoming calls, directing inquiries appropriately. Support special projects and company events as needed.",
            'salary_from' => 45000,
            'salary_to'   => 60000,
        ],
        [
            'title' => 'Electrician',
            'requirement' => "Valid journeyman or master electrician license required. Minimum 4 years of experience in commercial or residential electrical installation and maintenance.\n\nThorough knowledge of the National Electrical Code (NEC) and local building codes. Ability to read and interpret blueprints, schematics, and wiring diagrams.\n\nValid driver's license. Ability to work at heights and in confined spaces. Commitment to safety and willingness to maintain required certifications.",
            'duty' => "Install, maintain, and repair electrical systems including wiring, panels, outlets, lighting, and control systems in commercial and residential settings.\n\nDiagnose electrical faults and perform troubleshooting to identify and resolve issues efficiently. Ensure all work complies with applicable codes, standards, and safety regulations. Coordinate with general contractors, project managers, and other trades on construction and renovation projects. Complete accurate job documentation including time sheets, material usage, and inspection reports.",
            'salary_from' => 62000,
            'salary_to'   => 88000,
        ],
    ];

    public function definition(): array
    {
        $job = fake()->randomElement(self::$jobs);

        // 80% chance of having a salary
        $hasSalary = fake()->boolean(80);

        return [
            'idcode'       => 'job_' . Str::uuid()->toString(),
            'company_user_id' => User::factory()->company(),
            'title'        => $job['title'],
            'requirement'  => $job['requirement'],
            'duty'         => $job['duty'],
            'salary_from'  => $hasSalary ? $job['salary_from'] : null,
            // 20% chance of single value (no upper bound)
            'salary_to'    => $hasSalary && fake()->boolean(80) ? $job['salary_to'] : null,
        ];
    }
}
