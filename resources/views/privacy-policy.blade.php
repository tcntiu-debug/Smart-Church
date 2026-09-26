@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb pl-0">
                <li class="breadcrumb-item"><a href="{{ url('/home') }}"><i class="material-icons">home</i> Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Privacy Policy</li>
            </ol>
        </nav>

        <div class="ms-panel">
            <div class="ms-panel-header">
                <h6>Privacy Policy for TCNIKD Smart Church App</h6>
                <p class="mb-0"><strong>Last Updated:</strong> December 30, 2024</p>
            </div>

            <div class="ms-panel-body">
                <div class="accordion" id="accordionExample1">
                    <div class="card">
                        <div class="card-header" data-toggle="collapse" role="button" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                            <span>1. Introduction</span>
                        </div>
                        <div id="collapseOne" class="collapse show" data-parent="#accordionExample1">
                            <div class="card-body">
                                Welcome to the TCNIKD Smart Church App. This Privacy Policy outlines how we safeguard every personal information collected when you register or are registered on our app.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion" id="accordionExample2">
                    <div class="card">
                        <div class="card-header" data-toggle="collapse" role="button" data-target="#collapseTwo" aria-expanded="true" aria-controls="collapseTwo">
                            <span>2. Information We Collect</span>
                        </div>
                        <div id="collapseTwo" class="collapse show" data-parent="#accordionExample2">
                            <div class="card-body">
                                We may collect the following personal information: Name, Phone Number, Email Address, Gender, Occupation, Age Range, Guest Type.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion" id="accordionExample3">
                    <div class="card">
                        <div class="card-header" data-toggle="collapse" role="button" data-target="#collapseThree" aria-expanded="true" aria-controls="collapseThree">
                            <span>3. Purpose of Data Collection</span>
                        </div>
                        <div id="collapseThree" class="collapse show" data-parent="#accordionExample3">
                            <div class="card-body">
                                The personal data collected is used for the following purposes: <br><br>
                                I. Communication with users in accordance with the rules of tracking and integration unit and TCN Ikorodu, <br><br>
                                II. Identification and authentication <br><br>
                                III. Providing relevant updates and notifications in accordance with TCN Ikorodu
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion" id="accordionExample4">
                    <div class="card">
                        <div class="card-header" data-toggle="collapse" role="button" data-target="#collapseFour" aria-expanded="true" aria-controls="collapseFour">
                            <span>4. Data Sharing and Disclosure</span>
                        </div>
                        <div id="collapseFour" class="collapse show" data-parent="#accordionExample4">
                            <div class="card-body">
                                We do not sell, rent, or share your personal information with third parties.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion" id="accordionExample5">
                    <div class="card">
                        <div class="card-header" data-toggle="collapse" role="button" data-target="#collapseFive" aria-expanded="true" aria-controls="collapseFive">
                            <span>5. Data Security</span>
                        </div>
                        <div id="collapseFive" class="collapse show" data-parent="#accordionExample5">
                            <div class="card-body">
                                We implement appropriate technical and organizational measures to ensure the security and confidentiality of your personal data.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion" id="accordionExample6">
                    <div class="card">
                        <div class="card-header" data-toggle="collapse" role="button" data-target="#collapseSix" aria-expanded="true" aria-controls="collapseSix">
                            <span>6. Data Retention</span>
                        </div>
                        <div id="collapseSix" class="collapse show" data-parent="#accordionExample6">
                            <div class="card-body">
                                Your data will be retained only for as long as necessary to fulfill the purposes outlined in this policy unless a longer retention period is required by TCN Ikorodu.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion" id="accordionExample7">
                    <div class="card">
                        <div class="card-header" data-toggle="collapse" role="button" data-target="#collapseSeven" aria-expanded="true" aria-controls="collapseSeven">
                            <span>7. Your Rights</span>
                        </div>
                        <div id="collapseSeven" class="collapse show" data-parent="#accordionExample7">
                            <div class="card-body">
                                You have the right to:<br><br>
                                Access, update, or delete your personal information.<br><br>
                                To exercise these rights, please contact us at: tcntiu@gmail.com.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion" id="accordionExample8">
                    <div class="card">
                        <div class="card-header" data-toggle="collapse" role="button" data-target="#collapseEight" aria-expanded="true" aria-controls="collapseEight">
                            <span>8. Changes to This Policy</span>
                        </div>
                        <div id="collapseEight" class="collapse show" data-parent="#accordionExample8">
                            <div class="card-body">
                                We may update this Privacy Policy from time to time. Any changes will be posted in the app, and we encourage you to review this policy regularly.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion" id="accordionExample9">
                    <div class="card">
                        <div class="card-header" data-toggle="collapse" role="button" data-target="#collapseNine" aria-expanded="true" aria-controls="collapseNine">
                            <span>9. Contact Us</span>
                        </div>
                        <div id="collapseNine" class="collapse show" data-parent="#accordionExample9">
                            <div class="card-body">
                                If you have any questions or concerns regarding this Privacy Policy, please contact us at: tcntiu@gmail.com. <br><br>
                                By using this App, you agree to the terms outlined in this Privacy Policy. <br><br>
                                Thank you for using the TCNIKD Smart Church App.
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <p class="text-muted">
                    <i class="fas fa-info-circle mr-1"></i>
                    By using the TCNIKD Smart Church App, you agree to the terms outlined in this Privacy Policy.
                </p>

            </div>
        </div>
    </div>
</div>
@endsection
