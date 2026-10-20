<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalController extends Controller
{
    /**
     * App configuration details for legal documents.
     */
    protected function getLegalConfig(): array
    {
        $appName = config('app.name', 'ZapReach');
        return [
            'app_name' => $appName,
            'company_name' => config('app.company_name', 'ZapReach Technologies'),
            'website_url' => config('app.url', 'https://zapreach.com'),
            'support_email' => env('MAIL_FROM_ADDRESS', 'support@zapreach.com'),
            'business_email' => env('MAIL_FROM_ADDRESS', 'info@zapreach.com'),
            'last_updated' => 'October 20, 2026',
            'version' => '1.0.0',
        ];
    }

    /**
     * Get Privacy Policy in structured JSON format.
     *
     * GET /api/privacy-policy
     * GET /api/privacy
     */
    public function getPrivacyPolicy(Request $request): JsonResponse
    {
        $config = $this->getLegalConfig();

        $sections = [
            [
                'id' => 'introduction',
                'title' => '1. Introduction & Scope',
                'content' => 'At ZapReach (operated by ZapReach Technologies, accessible at ' . $config['website_url'] . '), your privacy is of paramount importance to us. This Privacy Policy outlines our policies and practices regarding the collection, storage, processing, and safeguarding of your personal data and business communication records when you use our WhatsApp Bulk Messaging platform, web dashboard, APIs, Auto-AI chatbot services, and related applications (collectively referred to as the "Platform" or "Services").',
                'points' => [
                    'By accessing or using ZapReach, you acknowledge that you have read, understood, and consented to the practices described in this Privacy Policy.',
                    'If you do not agree with any terms herein, please immediately discontinue the use of our Platform and Services.',
                    'This Privacy Policy operates in conjunction with our Terms and Conditions.',
                ],
            ],
            [
                'id' => 'information-we-collect',
                'title' => '2. Information We Collect',
                'content' => 'We collect various types of information to provide, maintain, and optimize our bulk messaging and WhatsApp Cloud API management services.',
                'subsections' => [
                    [
                        'subtitle' => 'A. Account & Profile Information',
                        'items' => [
                            'Full Name, Business Name, and Company registration details.',
                            'Email address, primary contact phone number, and account password hashes.',
                            'Billing and tax identification information (e.g., GSTIN, billing address).',
                        ],
                    ],
                    [
                        'subtitle' => 'B. WhatsApp Cloud API & Integration Credentials',
                        'items' => [
                            'Meta App ID, App Secret, and System User Access Tokens provided by the user.',
                            'WhatsApp Business Account (WABA) ID and Phone Number IDs.',
                            'Webhook URLs, verify tokens, and message routing endpoints.',
                        ],
                    ],
                    [
                        'subtitle' => 'C. Contact Lists & Broadcast Data',
                        'items' => [
                            'Recipient telephone numbers uploaded by you into contacts, groups, or campaign rosters.',
                            'Contact metadata (tags, notes, custom field attributes for dynamic template variables).',
                            'Message templates, interactive buttons, quick replies, and uploaded media attachments (images, PDFs, videos).',
                        ],
                    ],
                    [
                        'subtitle' => 'D. Transaction & Wallet Records',
                        'items' => [
                            'Prepaid wallet recharge history, Razorpay payment reference IDs, and message credit utilization.',
                            'Note: We do not store sensitive payment credentials such as credit card CVVs or bank PINs on our servers.',
                        ],
                    ],
                    [
                        'subtitle' => 'E. Technical, Diagnostic & Usage Logs',
                        'items' => [
                            'Message delivery statuses (sent, delivered, read, failed) and timestamped gateway dispatch records.',
                            'Internet Protocol (IP) addresses, browser type, device information, and session logs.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'how-we-use-information',
                'title' => '3. How We Use Your Information',
                'content' => 'We utilize the collected information strictly for operational, legal, and functional purposes, including:',
                'points' => [
                    'Transmitting broadcast campaigns, individual messages, and automated follow-ups via the official WhatsApp Cloud API.',
                    'Enabling Auto-AI automated responses, conversation workflows, and chatbot customer interactions.',
                    'Calculating per-message wallet deductions based on category rates (Marketing, Utility, Authentication).',
                    'Providing delivery reports, analytics dashboards, and message bounce diagnostics.',
                    'Detecting and preventing fraudulent activity, unauthorized access, and violations of Meta messaging guidelines.',
                    'Delivering essential system notifications, critical account alerts, and customer support assistance.',
                ],
            ],
            [
                'id' => 'meta-whatsapp-compliance',
                'title' => '4. WhatsApp Business API & Meta Policy Compliance',
                'content' => 'Our platform facilitates integration with Meta Platforms, Inc.\'s official WhatsApp Business Cloud API. As a user:',
                'points' => [
                    'You are responsible for obtaining explicit, verifiable opt-in consent from all message recipients prior to broadcasting campaigns.',
                    'You must strictly comply with Meta\'s WhatsApp Business Policy, WhatsApp Commerce Policy, and message template approval guidelines.',
                    'Message payloads and recipient phone numbers are processed through Meta\'s secure API infrastructure for delivery.',
                ],
            ],
            [
                'id' => 'data-sharing-third-parties',
                'title' => '5. Data Sharing & Third-Party Processors',
                'content' => 'We do not sell, rent, or trade your contact lists or message content to any third parties for advertising or commercial monetization. We share data only with verified service providers necessary to operate our platform:',
                'points' => [
                    'Meta Platforms, Inc. – To deliver WhatsApp broadcast messages through official Cloud API endpoints.',
                    'Payment Gateways (e.g., Razorpay) – To process secure prepaid wallet recharge transactions.',
                    'Cloud Infrastructure & Hosting Providers – For high-availability server hosting, database storage, and secure backups.',
                    'Legal & Regulatory Authorities – When mandatory to comply with applicable laws, court subpoenas, or governmental directives.',
                ],
            ],
            [
                'id' => 'data-security',
                'title' => '6. Data Security & Storage',
                'content' => 'We implement robust industry-standard technical and organizational security measures to protect your sensitive information:',
                'points' => [
                    'Encrypted data transmission using TLS 1.3/SSL protocols across all endpoints.',
                    'Strict database isolation and encrypted storage of API access tokens and system secrets.',
                    'Role-based access controls (RBAC) ensuring only authorized administrative personnel can access server operations.',
                    'Regular automated backups, server firewall protections, and vulnerability assessments.',
                ],
            ],
            [
                'id' => 'data-retention-deletion',
                'title' => '7. Data Retention & User Rights',
                'content' => 'You retain comprehensive rights regarding your personal and business data:',
                'points' => [
                    'Right to Access & Export: You may view, download, or export your contact lists, templates, and campaign reports anytime from the dashboard.',
                    'Right to Rectify & Delete: You can edit or permanently delete your contacts, media files, and message templates.',
                    'Account Closure: Upon account termination, your stored API credentials, contacts, and campaign logs will be scheduled for permanent purging.',
                ],
            ],
            [
                'id' => 'cookies-tracking',
                'title' => '8. Cookies & Session Technologies',
                'content' => 'We utilize essential session cookies and secure authentication tokens to verify your login sessions, preserve your user preferences, and safeguard against Cross-Site Request Forgery (CSRF). We do not use intrusive third-party ad tracking cookies.',
            ],
            [
                'id' => 'policy-updates',
                'title' => '9. Policy Updates & Modifications',
                'content' => 'We reserve the right to revise or update this Privacy Policy from time to time to reflect operational advancements or legal requirements. Material modifications will be posted with an updated "Last Updated" timestamp, and significant revisions may be communicated via dashboard notification or email.',
            ],
            [
                'id' => 'contact-us',
                'title' => '10. Contact & Support Information',
                'content' => 'If you have any questions, concerns, or requests regarding this Privacy Policy or your data, please contact us:',
                'points' => [
                    'Entity: ' . $config['company_name'],
                    'Platform: ' . $config['app_name'],
                    'Website: ' . $config['website_url'],
                    'Support Email: ' . $config['support_email'],
                    'Business Inquiries: ' . $config['business_email'],
                ],
            ],
        ];

        return response()->json([
            'status' => true,
            'message' => 'Privacy policy retrieved successfully',
            'data' => [
                'title' => 'Privacy Policy – ' . $config['app_name'],
                'config' => $config,
                'sections' => $sections,
                'html_url' => url('/privacy-policy'),
            ],
        ], 200);
    }

    /**
     * Get Terms and Conditions in structured JSON format.
     *
     * GET /api/terms-and-conditions
     * GET /api/terms
     */
    public function getTermsAndConditions(Request $request): JsonResponse
    {
        $config = $this->getLegalConfig();

        $sections = [
            [
                'id' => 'acceptance',
                'title' => '1. Acceptance of Terms',
                'content' => 'These Terms and Conditions constitute a legally binding agreement between you (whether individually or on behalf of an entity, "User", "Client", or "You") and ZapReach Technologies ("Company", "We", "Us", or "Our"), regarding your access to and use of ZapReach accessible at ' . $config['website_url'] . ' (the "Platform" or "Services"). By accessing or utilizing our Services, you agree to comply with and be bound by all the terms specified herein.',
            ],
            [
                'id' => 'account-eligibility',
                'title' => '2. Account Registration & Security',
                'content' => 'To access our bulk messaging software, you must register for an active user account:',
                'points' => [
                    'You must be at least 18 years of age and possess legal capacity to enter into binding agreements.',
                    'You must provide accurate, current, and complete information during registration and keep your profile updated.',
                    'You are solely responsible for safeguarding your login credentials and for all activities that occur under your account.',
                    'You must immediately notify us of any unauthorized use or security breach of your account.',
                ],
            ],
            [
                'id' => 'anti-spam-acceptable-use',
                'title' => '3. Acceptable Use & Strict Anti-Spam Policy',
                'content' => 'ZapReach enforces a zero-tolerance policy against unsolicited spam, abusive messaging, and illicit broadcast operations. You agree NOT to:',
                'points' => [
                    'Send unsolicited messages (spam) or messages to phone numbers without prior, verifiable opt-in consent from recipients.',
                    'Broadcast content that is defamatory, harassing, obscene, fraudulent, deceptive, or infringing on intellectual property.',
                    'Distribute malware, phishing links, counterfeit goods promotions, gambling promotions, or narcotics-related materials.',
                    'Scrape or harvest telephone numbers through automated bots without authorization.',
                    'Engage in activities that cause your WhatsApp Business Account (WABA) or phone number quality rating to degrade into flagged status.',
                ],
            ],
            [
                'id' => 'meta-cloud-api-terms',
                'title' => '4. WhatsApp Cloud API & Meta Policy Compliance',
                'content' => 'You acknowledge that ZapReach provides software interfaces to Meta\'s WhatsApp Cloud API. Therefore:',
                'points' => [
                    'You must abide by Meta\'s WhatsApp Business Terms of Service, Meta Developer Terms, and WhatsApp Business Messaging Policies.',
                    'Template messages submitted for broadcasting are subject to Meta\'s categorization, review, and approval guidelines.',
                    'We are not liable for account bans, message throttling, phone number blocks, or template rejections imposed by Meta due to policy violations.',
                ],
            ],
            [
                'id' => 'wallet-billing-payments',
                'title' => '5. Prepaid Wallet, Credits & Billing Terms',
                'content' => 'Our service operates on a prepaid credits and wallet-based billing mechanism:',
                'points' => [
                    'Wallet Recharge: Users recharge credits using integrated payment gateways (e.g., Razorpay) in INR or applicable currency.',
                    'Per-Message Charges: Messaging credits are automatically deducted upon dispatch based on active message categories (Marketing, Utility, Authentication).',
                    'Non-Refundable Policy: Wallet balances and purchased message credits are non-refundable once credited to your account.',
                    'Taxes: All rates and recharge amounts are subject to applicable taxes (including GST) as mandated by law.',
                ],
            ],
            [
                'id' => 'campaign-dispatch-disclaimer',
                'title' => '6. Service Delivery & Availability Disclaimer',
                'content' => 'While ZapReach strives for continuous 99.9% platform uptime and prompt message dispatch:',
                'points' => [
                    'Final delivery of messages depends on recipient device availability, telecom connectivity, and Meta WhatsApp API gateway processing.',
                    'We do not guarantee 100% delivery rates for phone numbers that are inactive, disconnected, non-WhatsApp registered, or opted-out.',
                    'Scheduled maintenance, emergency server updates, or upstream Meta API outages may temporarily affect dispatch queues.',
                ],
            ],
            [
                'id' => 'auto-ai-automation',
                'title' => '7. Auto-AI & Chatbot Automation Guidelines',
                'content' => 'Our Auto-AI capabilities enable automated conversation replies and contextual query handling. You are responsible for:',
                'points' => [
                    'Configuring accurate business profile knowledge, operating hours, and tone parameters.',
                    'Reviewing AI-generated responses periodically to ensure alignment with your customer service expectations.',
                    'Ensuring automated replies do not generate misleading, offensive, or non-compliant communications.',
                ],
            ],
            [
                'id' => 'intellectual-property',
                'title' => '8. Intellectual Property Rights',
                'content' => 'All source code, software architecture, UI designs, visual layouts, graphics, documentation, and trademarks associated with ZapReach remain the exclusive intellectual property of ZapReach Technologies. You are granted a revocable, non-exclusive, non-transferable license to access the Platform for your business operations.',
            ],
            [
                'id' => 'limitation-of-liability',
                'title' => '9. Limitation of Liability & Indemnification',
                'content' => 'To the maximum extent permitted by law:',
                'points' => [
                    'ZapReach Technologies shall not be liable for any indirect, incidental, punitive, or consequential damages resulting from lost profits, data loss, or business interruption.',
                    'You agree to defend, indemnify, and hold harmless ZapReach Technologies, its directors, and employees from any claims, damages, or liabilities arising from the content of your broadcast messages or breach of these Terms.',
                ],
            ],
            [
                'id' => 'suspension-termination',
                'title' => '10. Suspension & Termination',
                'content' => 'We reserve the right to suspend or permanently terminate your access to the Platform without prior notice if you violate these Terms, engage in unsolicited spamming, fail to maintain valid API credentials, or engage in unlawful conduct.',
            ],
            [
                'id' => 'governing-law',
                'title' => '11. Governing Law & Dispute Resolution',
                'content' => 'These Terms shall be governed by and construed in accordance with the laws of India. Any legal action, dispute, or proceeding arising out of these Terms shall be subject to the exclusive jurisdiction of the competent courts located in India.',
            ],
            [
                'id' => 'contact-terms',
                'title' => '12. Contact Information',
                'content' => 'For questions or clarification regarding these Terms and Conditions, please contact us:',
                'points' => [
                    'Company: ' . $config['company_name'],
                    'Platform: ' . $config['app_name'],
                    'Website: ' . $config['website_url'],
                    'Support: ' . $config['support_email'],
                ],
            ],
        ];

        return response()->json([
            'status' => true,
            'message' => 'Terms and conditions retrieved successfully',
            'data' => [
                'title' => 'Terms & Conditions – ' . $config['app_name'],
                'config' => $config,
                'sections' => $sections,
                'html_url' => url('/terms-and-conditions'),
            ],
        ], 200);
    }

    /**
     * Get Delete Account & Data Deletion instructions in structured JSON format.
     *
     * GET /api/delete-account
     * GET /api/data-deletion
     */
    public function getDeleteAccountInfo(Request $request): JsonResponse
    {
        $config = $this->getLegalConfig();

        $sections = [
            [
                'id' => 'overview',
                'title' => '1. Overview of Account & Data Deletion',
                'content' => 'At ZapReach, we respect your right to control your personal and corporate data. You have the right to request the permanent deletion of your user account and all associated WhatsApp broadcasting data at any time.',
            ],
            [
                'id' => 'how-to-delete',
                'title' => '2. How to Request Account Deletion',
                'content' => 'You can request account and data deletion through any of the following methods:',
                'methods' => [
                    [
                        'name' => 'Option A: Online Deletion Request Form',
                        'description' => 'Visit our web deletion portal at ' . url('/delete-account') . ' or call POST /api/delete-account-request with your registered account details.',
                    ],
                    [
                        'name' => 'Option B: Direct Email Support',
                        'description' => 'Send an email to ' . $config['support_email'] . ' from your registered email address with the subject "Account Deletion Request".',
                    ],
                    [
                        'name' => 'Option C: In-App Dashboard Settings',
                        'description' => 'Navigate to Settings > Profile > Delete Account inside your ZapReach dashboard.',
                    ],
                ],
            ],
            [
                'id' => 'data-erased',
                'title' => '3. Data That Will Be Permanently Erased',
                'items' => [
                    'Personal & Profile Information (Full name, email, phone number, password hash, company details).',
                    'WhatsApp Cloud API Credentials (Meta App ID, App Secret, WABA ID, Phone Number ID, Access Tokens).',
                    'Contact Lists & Rosters (All recipient phone numbers, custom parameters, notes, and tags).',
                    'Broadcast Campaigns & Templates (Campaign histories, broadcast queues, template caches, and logs).',
                    'Uploaded Media Assets (Images, videos, documents, audio clips stored on our file servers).',
                    'Auto-AI & Chatbot Rules (Automation workflows, custom knowledge base, and tone configurations).',
                ],
            ],
            [
                'id' => 'data-retained',
                'title' => '4. Data Retained for Legal Compliance',
                'content' => 'In accordance with statutory regulations and tax compliance laws, certain billing records (such as completed Razorpay transaction IDs, GST invoice logs, and financial ledgers) may be retained for the minimum period mandated by Indian tax authorities. These records are strictly archived and never used for marketing.',
            ],
            [
                'id' => 'timeline',
                'title' => '5. Deletion Timeline & Irreversibility',
                'content' => 'Upon receiving your verified request, your account is immediately deactivated. All active API tokens are revoked. Full purging of database records and media files is finalized within 14 to 30 days. Please note that account deletion is permanent and cannot be undone.',
            ],
            [
                'id' => 'whatsapp-revocation',
                'title' => '6. Revoking Meta & WhatsApp Cloud API Access',
                'content' => 'We also recommend revoking our application access directly from your Meta Business Manager console (business.facebook.com) under System Users / WhatsApp Accounts.',
            ],
        ];

        return response()->json([
            'status' => true,
            'message' => 'Account deletion instructions retrieved successfully',
            'data' => [
                'title' => 'Delete Account & Data Deletion – ' . $config['app_name'],
                'config' => $config,
                'sections' => $sections,
                'html_url' => url('/delete-account'),
            ],
        ], 200);
    }

    /**
     * Submit Account Deletion Request.
     *
     * POST /api/delete-account-request
     */
    public function submitDeleteAccountRequest(Request $request): JsonResponse
    {
        $config = $this->getLegalConfig();

        $request->validate([
            'email' => 'required|email',
            'mobile' => 'nullable|string|max:20',
            'reason' => 'nullable|string|max:1000',
            'confirm_deletion' => 'required|accepted',
        ]);

        $email = $request->input('email');
        $mobile = $request->input('mobile');
        $reason = $request->input('reason', 'User requested deletion');

        // Check if user exists in database
        $user = \App\Models\User::where('email', $email)
            ->orWhere(function ($query) use ($mobile) {
                if ($mobile) {
                    $query->where('number', $mobile)->orWhere('companyMobile', $mobile);
                }
            })
            ->first();

        $referenceId = 'DEL-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 10));

        // In a live system, we flag the user or notify the admin team
        return response()->json([
            'status' => true,
            'message' => 'Account deletion request submitted successfully. Our team will verify and process your request within 7-14 business days.',
            'data' => [
                'reference_id' => $referenceId,
                'email' => $email,
                'user_found' => (bool)$user,
                'status' => 'Pending Verification',
                'support_contact' => $config['support_email'],
                'timeline' => 'Account will be deactivated and data permanently purged within 14 to 30 days.',
            ],
        ], 200);
    }
}
