<div class="apply-panel overflow-hidden">
                    <div class="bg-gradient-to-r from-brand-blue to-[#0a3578] text-white px-5 md:px-7 py-6">
                        <p class="text-brand-gold text-xs font-bold uppercase tracking-wider m-0">Application form</p>
                        <h2 class="text-2xl font-extrabold m-0 mt-1">{{ $isInternship ? 'Internship application' : 'Job application' }}</h2>
                        <p class="text-blue-100 text-sm m-0 mt-1">
                            @if($isInternship)
                                Start by selecting your program, then complete your details.
                            @else
                                Fill in your details and submit your documents.
                            @endif
                        </p>
                    </div>

                    @if ($errors->any())
                        <div class="mx-5 md:mx-7 mt-5 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
                            <ul class="list-disc pl-5 space-y-1 mb-0">
                                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('apply.store', $job->id) }}" enctype="multipart/form-data"
                          class="p-5 md:p-7 space-y-7" id="apply-form"
                          x-data="{
                              availability: '{{ old('availability', 'Immediately') }}',
                              educationStatus: '{{ old('education_status', 'currently_studying') }}',
                              academicRequired: '{{ old('is_academic_required', '1') }}',
                              selectedProgram: '{{ old('internship_program_id', '') }}'
                          }">
                        @csrf

                        @if($isInternship && $offerPrograms->isNotEmpty())
                        <div class="rounded-2xl border-2 border-brand-blue/20 bg-gradient-to-b from-[#eef4ff] to-white p-4 md:p-5">
                            <div class="mb-4">
                                <p class="text-xs font-bold uppercase tracking-wider text-brand-gold m-0">Step 1</p>
                                <h3 class="text-lg font-extrabold text-brand-blue m-0 mt-1">Select internship program *</h3>
                                <p class="text-sm text-gray-600 m-0 mt-1">Tap the track you want to join if accepted.</p>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($offerPrograms as $prog)
                                    <label class="apply-prog relative"
                                           :class="String(selectedProgram) === '{{ $prog->id }}' ? 'is-on' : ''">
                                        <input type="radio" name="internship_program_id" value="{{ $prog->id }}" required
                                               x-model="selectedProgram"
                                               @if((string) old('internship_program_id') === (string) $prog->id) checked @endif>
                                        <span class="flex items-start justify-between gap-2">
                                            <span>
                                                <span class="block font-bold text-brand-blue text-sm">{{ $prog->displayName() }}</span>
                                                @if(!empty($prog->code))
                                                    <span class="block text-xs text-gray-500 mt-0.5">{{ $prog->code }}</span>
                                                @endif
                                            </span>
                                            <span class="mt-0.5 h-5 w-5 rounded-full border-2 flex items-center justify-center shrink-0"
                                                  :class="String(selectedProgram) === '{{ $prog->id }}' ? 'border-brand-blue bg-brand-blue' : 'border-slate-300'">
                                                <i data-lucide="check" class="w-3 h-3 text-white" x-show="String(selectedProgram) === '{{ $prog->id }}'"></i>
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('internship_program_id')
                                <p class="text-xs text-red-600 mt-2 mb-0">{{ $message }}</p>
                            @enderror

                            <div class="mt-5">
                                <label class="text-sm font-bold text-brand-blue">Internship duration (days) *</label>
                                <p class="text-xs text-gray-500 mt-0.5 mb-2">How long will you be with us? ({{ \App\Application::internshipDurationMin() }}–{{ \App\Application::internshipDurationMax() }} days)</p>
                                <input required type="number" name="internship_duration_days"
                                       min="{{ \App\Application::internshipDurationMin() }}"
                                       max="{{ \App\Application::internshipDurationMax() }}"
                                       value="{{ old('internship_duration_days') }}"
                                       placeholder="e.g. 21, 30, 90"
                                       class="w-full sm:max-w-xs rounded-xl border border-slate-200 px-4 py-3 focus:border-brand-blue outline-none bg-white">
                                @error('internship_duration_days')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        @elseif($isInternship)
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                No internship programs are published yet. Please contact the administrator before applying.
                            </div>
                        @endif

                        <div>
                            <h3 class="text-sm font-extrabold uppercase tracking-wide text-brand-blue mb-3 pb-2 border-b border-gray-100">
                                {{ $isInternship ? 'Step 2 · Contact information' : 'Contact information' }}
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="text-sm font-semibold text-gray-700">Full Name *</label>
                                    <input required name="full_name" value="{{ old('full_name') }}" type="text" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2.5 focus:border-brand-blue outline-none">
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-gray-700">Email *</label>
                                    <input required name="email" value="{{ old('email') }}" type="email" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2.5 focus:border-brand-blue outline-none">
                                </div>
                                <div>
                                    <label class="text-sm font-bold text-brand-blue">WhatsApp number *</label>
                                    <p class="text-xs text-gray-500 mt-0.5">Your only contact number — used for all application status notifications.</p>
                                    <div class="apply-phone-row">
                                        <select name="country_code" class="apply-phone-cc" aria-label="Country code">
                                            @foreach ($countryCodes as $code => $label)
                                                <option value="{{ $code }}" @if(old('country_code', '+237') === $code) selected @endif>{{ $code }}</option>
                                            @endforeach
                                        </select>
                                        <input required name="whatsapp_number" data-wa-phone="local"
                                               value="{{ old('whatsapp_number') }}"
                                               type="tel" inputmode="tel" placeholder="675321739"
                                               autocomplete="tel-national"
                                               class="apply-phone-local @error('whatsapp_number') border-red-500 @enderror">
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Use a full mobile number (e.g. 675321739).</p>
                                    @error('whatsapp_number')
                                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                                @unless($isInternship)
                                    <div>
                                        <label class="text-sm font-semibold text-gray-700">Expected Salary (optional)</label>
                                        <input name="expected_salary" value="{{ old('expected_salary') }}" type="text" placeholder="e.g. 600,000 RWF" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2.5 focus:border-brand-blue outline-none">
                                    </div>
                                @endunless
                                <div>
                                    <label class="text-sm font-bold text-brand-blue">When can you start?</label>
                                    <p class="text-xs text-gray-500 mt-0.5 mb-0">Choose how soon you can begin.</p>
                                    <input type="hidden" name="availability" :value="availability">
                                    <div class="apply-avail" role="radiogroup" aria-label="Availability">
                                        @foreach ([
                                            ['Immediately', 'Immediately'],
                                            ['1 week', '1 week'],
                                            ['2 weeks', '2 weeks'],
                                            ['1 month', '1 month'],
                                            ['Custom', 'Custom days'],
                                        ] as [$value, $label])
                                            <label :class="availability === '{{ $value }}' ? 'is-on' : ''">
                                                <input type="radio" value="{{ $value }}" x-model="availability"
                                                       @if(old('availability', 'Immediately') === $value) checked @endif>
                                                {{ $label }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div x-show="availability === 'Custom'" x-cloak x-transition class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                                    <label class="text-sm font-bold text-brand-blue">Available in (days)</label>
                                    <p class="text-xs text-gray-500 mt-0.5 mb-0">Enter how many days from today until you can start.</p>
                                    <div class="apply-days"
                                         x-data="{
                                             days: {{ (int) old('availability_days', 14) ?: 14 }},
                                             bump(d) { this.days = Math.min(365, Math.max(1, Number(this.days || 1) + d)); }
                                         }">
                                        <button type="button" @click="bump(-1)" aria-label="Decrease days">−</button>
                                        <input name="availability_days" type="number" min="1" max="365"
                                               x-model.number="days" inputmode="numeric">
                                        <button type="button" @click="bump(1)" aria-label="Increase days">+</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($isInternship)
                        <div>
                            <h3 class="text-sm font-extrabold uppercase tracking-wide text-brand-blue mb-3 pb-2 border-b border-gray-100">Step 3 · Education</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="text-sm font-semibold text-gray-700">School / Institution *</label>
                                    <input required name="school" value="{{ old('school') }}" type="text" placeholder="e.g. University of Bamenda" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2.5 focus:border-brand-blue outline-none">
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-gray-700">Level of study *</label>
                                    <select required name="level_of_study" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2.5 focus:border-brand-blue outline-none">
                                        <option value="">Select level…</option>
                                        @foreach (['Secondary / High School', 'Certificate', 'Diploma / HND', 'Bachelor’s degree', 'Master’s degree', 'PhD / Doctorate', 'Other'] as $level)
                                            <option value="{{ $level }}" @if(old('level_of_study') === $level) selected @endif>{{ $level }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-gray-700">Are you still a student or have you graduated? *</label>
                                    <select required name="education_status" x-model="educationStatus" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2.5 focus:border-brand-blue outline-none">
                                        <option value="currently_studying" @if(old('education_status', 'currently_studying') === 'currently_studying') selected @endif>Currently studying</option>
                                        <option value="graduated" @if(old('education_status') === 'graduated') selected @endif>Graduated</option>
                                    </select>
                                </div>
                                <div x-show="educationStatus === 'currently_studying'" x-cloak>
                                    <label class="text-sm font-semibold text-gray-700">Is this an academic-required internship? *</label>
                                    <p class="text-xs text-gray-500 mt-0.5">Choose Yes if your school/programme requires this internship for graduation.</p>
                                    <select name="is_academic_required" x-model="academicRequired"
                                            x-bind:disabled="educationStatus !== 'currently_studying'"
                                            class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2.5 focus:border-brand-blue outline-none">
                                        <option value="1">Yes — required by my school / programme</option>
                                        <option value="0">No — voluntary / personal development</option>
                                    </select>
                                </div>
                                <template x-if="educationStatus === 'graduated'">
                                    <input type="hidden" name="is_academic_required" value="0">
                                </template>
                                <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-3 py-2 mb-0"
                                   x-show="educationStatus === 'currently_studying' && academicRequired === '1'" x-cloak>
                                    An internship letter from your school is required for academic internships.
                                </p>
                            </div>
                        </div>
                        @endif

                        <div>
                            <h3 class="text-sm font-extrabold uppercase tracking-wide text-brand-blue mb-3 pb-2 border-b border-gray-100">
                                {{ $isInternship ? 'Step 4 · Documents' : 'Documents' }}
                            </h3>
                            @unless($isInternship)
                                <div>
                                    <label class="text-sm font-semibold text-gray-700">Resume / CV (PDF, DOC, DOCX) *</label>
                                    <input required name="cv" type="file" accept=".pdf,.doc,.docx"
                                           class="w-full mt-1 text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-brand-blue file:text-white file:font-semibold hover:file:bg-brand-dark">
                                </div>
                            @else
                                <div class="mb-4">
                                    <label class="text-sm font-semibold text-gray-700">Resume / CV (optional)</label>
                                    <input name="cv" type="file" accept=".pdf,.doc,.docx"
                                           class="w-full mt-1 text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-brand-blue file:text-white file:font-semibold hover:file:bg-brand-dark">
                                    <p class="text-xs text-gray-500 mt-1">Not required for internships.</p>
                                </div>

                                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 space-y-4">
                                    <div>
                                        <p class="text-sm font-bold text-emerald-900">Internship documents</p>
                                        <p class="text-xs text-emerald-800 mt-1">Use <strong>Snap with camera</strong> or <strong>Attach file</strong> for each item.</p>
                                    </div>

                                    <div class="rounded-md border border-emerald-300 bg-white/80 p-3 space-y-3">
                                        <div>
                                            <p class="text-sm font-bold text-gray-800">National ID card *</p>
                                            <p class="text-xs text-gray-600 mt-1">Both sides are required — like a Cameroon National Identity Card:</p>
                                            <ul class="text-xs text-gray-600 mt-1 list-disc pl-4 space-y-0.5 mb-0">
                                                <li><strong>Front</strong> — photo, names, date of birth, chip side</li>
                                                <li><strong>Back</strong> — parents, address, issue/expiry dates, unique identifier</li>
                                            </ul>
                                        </div>
                                        @foreach ([
                                            ['student_id', 'ID card — Front', 'environment', 'Snap ID Front'],
                                            ['student_id_back', 'ID card — Back', 'environment', 'Snap ID Back'],
                                        ] as [$field, $label, $facing, $snapTitle])
                                            <div data-apply-doc data-facing="{{ $facing }}" data-title="{{ $snapTitle }}">
                                                <label class="text-sm font-semibold text-gray-700">{{ $label }} *</label>
                                                <input type="file" name="{{ $field }}" data-doc-target accept="image/*,.pdf" class="sr-only" tabindex="-1">
                                                <input type="file" data-doc-attach accept="image/*,.pdf" class="hidden" id="attach-{{ $field }}">
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    <label for="attach-{{ $field }}" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md border border-brand-blue text-brand-blue text-xs font-bold cursor-pointer bg-white hover:bg-blue-50">
                                                        <i data-lucide="paperclip" class="w-3.5 h-3.5"></i> Attach file
                                                    </label>
                                                    <button type="button" data-doc-snap class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md bg-brand-blue text-white text-xs font-bold hover:bg-brand-dark">
                                                        <i data-lucide="camera" class="w-3.5 h-3.5"></i> Snap with camera
                                                    </button>
                                                </div>
                                                <p class="text-xs text-emerald-700 mt-1.5 min-h-[1rem]" data-doc-status>No file yet</p>
                                                <img data-doc-preview alt="{{ $label }} preview" class="hidden mt-2 max-h-28 rounded-md border border-emerald-200 object-cover">
                                            </div>
                                        @endforeach
                                    </div>

                                    <div data-apply-doc data-facing="environment" data-title="Snap Internship Letter">
                                        <label class="text-sm font-semibold text-gray-700">
                                            Internship Letter
                                            <span x-text="(educationStatus === 'currently_studying' && academicRequired === '1') ? '*' : '(optional)'"></span>
                                        </label>
                                        <input type="file" name="internship_letter" data-doc-target accept="image/*,.pdf" class="sr-only" tabindex="-1">
                                        <input type="file" data-doc-attach accept="image/*,.pdf" class="hidden" id="attach-internship_letter">
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <label for="attach-internship_letter" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md border border-brand-blue text-brand-blue text-xs font-bold cursor-pointer bg-white hover:bg-blue-50">
                                                <i data-lucide="paperclip" class="w-3.5 h-3.5"></i> Attach file
                                            </label>
                                            <button type="button" data-doc-snap class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md bg-brand-blue text-white text-xs font-bold hover:bg-brand-dark">
                                                <i data-lucide="camera" class="w-3.5 h-3.5"></i> Snap with camera
                                            </button>
                                        </div>
                                        <p class="text-xs text-emerald-700 mt-1.5 min-h-[1rem]" data-doc-status>No file yet</p>
                                        <img data-doc-preview alt="Internship Letter preview" class="hidden mt-2 max-h-28 rounded-md border border-emerald-200 object-cover">
                                    </div>

                                    @foreach ([
                                        ['selfie', 'Selfie / Photo', 'user', 'Snap Selfie'],
                                    ] as [$field, $label, $facing, $snapTitle])
                                        <div data-apply-doc data-facing="{{ $facing }}" data-title="{{ $snapTitle }}">
                                            <label class="text-sm font-semibold text-gray-700">{{ $label }} *</label>
                                            <input type="file" name="{{ $field }}" data-doc-target accept="image/*{{ $field !== 'selfie' ? ',.pdf' : '' }}" class="sr-only" tabindex="-1">
                                            <input type="file" data-doc-attach accept="image/*{{ $field !== 'selfie' ? ',.pdf' : '' }}" class="hidden" id="attach-{{ $field }}">
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                <label for="attach-{{ $field }}" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md border border-brand-blue text-brand-blue text-xs font-bold cursor-pointer bg-white hover:bg-blue-50">
                                                    <i data-lucide="paperclip" class="w-3.5 h-3.5"></i> Attach file
                                                </label>
                                                <button type="button" data-doc-snap class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md bg-brand-blue text-white text-xs font-bold hover:bg-brand-dark">
                                                    <i data-lucide="camera" class="w-3.5 h-3.5"></i> Snap with camera
                                                </button>
                                            </div>
                                            <p class="text-xs text-emerald-700 mt-1.5 min-h-[1rem]" data-doc-status>No file yet</p>
                                            <img data-doc-preview alt="{{ $label }} preview" class="hidden mt-2 max-h-28 rounded-md border border-emerald-200 object-cover">
                                            @if ($field === 'selfie')
                                                <p class="text-xs text-gray-500 mt-1">Front camera opens for selfie; you can Flip to use the other camera.</p>
                                            @endif
                                        </div>
                                    @endforeach

                                    <div>
                                        <label class="text-sm font-semibold text-gray-700">Signature *</label>
                                        <canvas id="apply-signature-pad" class="w-full mt-1 border-2 border-dashed border-brand-gold rounded-md bg-white" style="height:140px;touch-action:none;"></canvas>
                                        <input type="hidden" name="signature_image" id="signature_image">
                                        <button type="button" id="clear-signature" class="mt-2 text-xs text-brand-blue underline">Clear signature</button>
                                    </div>
                                    <label class="flex items-start gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="agreement_accepted" value="1" class="mt-1" required>
                                        <span>I confirm my documents are accurate and I understand this internship is unpaid with required timesheets.</span>
                                    </label>
                                </div>
                            @endunless
                        </div>

                        <div>
                            <h3 class="text-sm font-extrabold uppercase tracking-wide text-brand-blue mb-3 pb-2 border-b border-gray-100">Cover letter (optional)</h3>
                            <textarea name="cover_letter" rows="4" placeholder="Tell us why you're a great fit..." class="w-full rounded-md border border-gray-200 px-3 py-2.5 focus:border-brand-blue outline-none">{{ old('cover_letter') }}</textarea>
                        </div>

                        <button type="submit" class="w-full bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-extrabold py-4 rounded-xl flex items-center justify-center gap-2 text-base shadow-md">
                            <i data-lucide="send" class="w-5 h-5"></i> Submit application
                        </button>
                        <p class="text-xs text-gray-500 text-center mb-0">You will be notified on WhatsApp when your application is under review.</p>
                    </form>
                </div>
