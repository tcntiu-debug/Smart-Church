@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb pl-0">
                <li class="breadcrumb-item"><a href="{{ url('/home') }}"><i class="material-icons">home</i> Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Policy Agreement</li>
            </ol>
        </nav>

        <div class="ms-panel">
            <div class="ms-panel-header">
                <h6>Data Privacy & Acceptable Use Policy</h6>
                <p class="mb-0"><strong>Policy Number:</strong> TCNIK-002</p>
                <p class="mb-0"><strong>Version:</strong> 2.1</p>
                <p class="mb-0"><strong>Effective Date:</strong> July 01, 2025</p>
                <p class="mb-0"><strong>Approved By:</strong> TCN Ikorodu Leadership</p>
            </div>
            <div class="ms-panel-body">
                <div class="accordion" id="accordionExample">
                    <div class="card">
                        <div class="card-header" role="button">
                            <span>Data Privacy & Acceptable Use Policy</span>
                        </div>
                        <div id="collapseInternalPolicy" class="collapse show">
                            <div class="card-body">
                                <h5>1.0 Purpose and Introduction</h5>
                                <p>TCN Ikorodu is committed to maintaining the trust, privacy, and security of all individuals within our community. This policy outlines the principles and strict procedures for handling the personal contact data of <strong>any individual</strong> (including Members, First-Timers, and Guests) accessed through the TCNIKD SMART Church App or church initiatives such as the Call Tree.</p>
                                <p>Access to personal contact information is a privilege granted solely for the purpose of ministry, community building, and official church communication. This policy is binding on all users of the TCNIKD SMART Church App.</p>

                                <h5>2.0 Scope</h5>
                                <p>This policy applies to:</p>
                                <ul>
                                    <li>All personal data (phone numbers, emails, addresses) accessed via the TCNIKD SMART Church App.</li>
                                    <li>All Guides, Workers, and Members participating in the Call Tree or Follow-up initiatives.</li>
                                </ul>

                                <h5>3.0 Core Data Protection Principles</h5>
                                <h6>3.1. Purpose Limitation & Prohibited Use</h6>
                                <p>Personal Data accessed through this platform shall be used for one purpose only: <strong>to facilitate official church integration, welfare checks (Call Tree), and community fellowship.</strong></p>

                                <p><strong>Authorized Use Includes:</strong></p>
                                <ul>
                                    <li>Contacting an individual to check on their welfare as assigned by the church (e.g., Call Tree).</li>
                                    <li>Following up with a First-Timer regarding their integration process.</li>
                                    <li><strong>Marketplace Transactions:</strong> Contacting a member specifically regarding a product or service <strong>they have voluntarily listed</strong> on the App's Marketplace.</li>
                                    <li>Informing members about relevant TCN Ikorodu events or group activities.</li>
                                </ul>

                                <p><strong><span class="text-danger">STRICTLY PROHIBITED</span> Use Includes:</strong></p>
                                <ul>
                                    <li><strong>Financial Solicitation:</strong> You may <strong>NOT</strong> use contact details obtained from this platform to ask for money, loans, financial aid, or donations for personal or external causes.</li>
                                    <li><strong>Unsolicited Marketing (Spam):</strong> You may <strong>NOT</strong> use contact lists (e.g., Call Tree assignments, First Timer lists) to cold-call or mass-message members about your business, products, or MLM schemes. Business promotion is <strong>ONLY</strong> allowed within the designated Marketplace section.</li>
                                    <li><strong>Data Sharing:</strong> You may <strong>NOT</strong> share another person's contact details with third parties without their explicit consent.</li>
                                </ul>

                                <h6>3.2. Data Retention and Deletion</h6>
                                <p>The church is committed to not holding personal data for longer than is necessary.</p>
                                <ul>
                                    <li><strong>For First-Timers:</strong> The standard Integration Period is 13 weeks. After unassignment, Guides must delete personal contact records from their personal devices.</li>
                                    <li><strong>For Call Tree Assignments:</strong> Contact details are provided for the specific week's assignment. Once the assignment is complete, the data should not be retained for unauthorized purposes.</li>
                                </ul>

                                <h5>4.0 User Responsibilities</h5>
                                <p>Every user with access to the TCNIKD SMART Church App must:</p>
                                <ul>
                                    <li>Read, understand, and agree to abide by this policy before being granted access.</li>
                                    <li>Respect the privacy and boundaries of the individuals they contact.</li>
                                    <li>Immediately report any suspected or actual misuse of data (e.g., being asked for money by someone who got your number from the app) to church leadership.</li>
                                </ul>

                                <h5>5.0 Policy Enforcement and Violations</h5>
                                <p>Any violation of this policy, particularly regarding financial solicitation or harassment, is a serious matter. <strong>Violators will have their access revoked immediately and may face further disciplinary action by the church authority.</strong></p>
                                <hr>

                                <!-- Agreement Section -->
                                @auth
                                    @if($hasSigned)
                                        <div class="alert alert-success mt-3">
                                            <i class="fas fa-check-circle mr-2"></i>
                                            <strong>You have already read and accepted this policy.</strong>
                                            @if(auth()->user()->policies()->where('signature', 'yes')->first())
                                                <br><small>Signed on: {{ auth()->user()->policies()->where('signature', 'yes')->first()->date_signed }}</small>
                                            @endif
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" checked disabled>
                                            <label class="form-check-label font-weight-bold">
                                                I have read, understand, and agree to this policy.
                                            </label>
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('policy.accept-data-policy') }}">
                                            @csrf
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" value="yes" id="policyAgree" name="agree" required>
                                                <label class="form-check-label font-weight-bold" for="policyAgree">
                                                    I have read, understand, and agree to this policy.
                                                </label>
                                            </div>
                                            <button type="submit" class="btn btn-primary" id="acceptBtn" disabled>
                                                <i class="fas fa-check-circle mr-2"></i>Accept Policy
                                            </button>
                                        </form>

                                        <script>
                                        document.addEventListener('DOMContentLoaded', function() {
                                            const checkbox = document.getElementById('policyAgree');
                                            const btn = document.getElementById('acceptBtn');
                                            if (checkbox && btn) {
                                                checkbox.addEventListener('change', function() {
                                                    btn.disabled = !this.checked;
                                                });
                                            }
                                        });
                                        </script>
                                    @endif
                                @else
                                <p class="text-muted mt-3">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <a href="{{ route('login') }}">Log in</a> to accept this policy.
                                </p>
                                @endauth

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
