@php
    $durationMin = \App\Application::internshipDurationMin();
    $durationMax = \App\Application::internshipDurationMax();
    $durationPresets = array_values(array_filter([21, 30, 60, 90, 180], function ($d) use ($durationMin, $durationMax) {
        return $d >= $durationMin && $d <= $durationMax;
    }));
@endphp
<div class="apply-panel overflow-hidden">
    <div class="bg-gradient-to-r from-brand-blue to-[#0a3578] text-white px-4 sm:px-7 py-5 sm:py-6">
        <p class="text-brand-gold text-xs font-bold uppercase tracking-wider m-0">Application form</p>
        <h2 class="text-xl sm:text-2xl font-extrabold m-0 mt-1">{{ $isInternship ? 'Internship application' : 'Job application' }}</h2>
        <p class="text-blue-100 text-sm m-0 mt-1 leading-relaxed">
            @if($isInternship)
                Complete each step below. Your WhatsApp number is used for all updates.
            @else
                Fill in your details and submit your documents.
            @endif
        </p>
    </div>

    @if ($isInternship)
        <div class="apply-progress">
            <div class="apply-progress-track"><div class="apply-progress-bar" id="apply-progress-bar"></div></div>
            <div class="apply-progress-meta">
                <span>Keep going — almost done</span>
                <span id="apply-progress-label">0% complete</span>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="mx-4 sm:mx-7 mt-4 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
            <p class="font-bold m-0 mb-1">Please fix the following:</p>
            <ul class="list-disc pl-5 space-y-1 mb-0">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('apply.store', $job->id) }}" enctype="multipart/form-data"
          class="p-4 sm:p-7 space-y-6 sm:space-y-7 apply-form-pad" id="apply-form"
          x-data="{
              availability: '{{ old('availability', 'Immediately') }}',
              educationStatus: '{{ old('education_status', 'currently_studying') }}',
              academicRequired: '{{ old('is_academic_required', '1') }}',
              selectedProgram: '{{ old('internship_program_id', '') }}',
              durationDays: '{{ old('internship_duration_days', '') }}',
              setDuration(d) { this.durationDays = String(d); }
          }">
        @csrf

        @if($isInternship && $offerPrograms->isNotEmpty())
        <div class="apply-step rounded-2xl border-2 border-brand-blue/20 bg-gradient-to-b from-[#eef4ff] to-white p-3.5 sm:p-5" id="step-program">
            <div class="apply-step-head border-0 pb-0 mb-3">
                <span class="apply-step-num">1</span>
                <div>
                    <h3 class="apply-step-title">Choose your program *</h3>
                    <p class="apply-step-sub">Tap the track you want if accepted</p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
                @foreach($offerPrograms as $prog)
                    <label class="apply-prog relative"
                           :class="String(selectedProgram) === '{{ $prog->id }}' ? 'is-on' : ''">
                        <input type="radio" name="internship_program_id" value="{{ $prog->id }}" required
                               x-model="selectedProgram"
                               @if((string) old('internship_program_id') === (string) $prog->id) checked @endif>
                        <span class="flex items-center justify-between gap-2">
                            <span class="min-w-0">
                                <span class="block font-bold text-brand-blue text-[0.95rem] leading-snug">{{ $prog->displayName() }}</span>
                                @if(!empty($prog->code))
                                    <span class="hidden sm:block text-xs text-gray-500 mt-0.5">{{ $prog->code }}</span>
                                @endif
                            </span>
                            <span class="h-6 w-6 rounded-full border-2 flex items-center justify-center shrink-0"
                                  :class="String(selectedProgram) === '{{ $prog->id }}' ? 'border-brand-blue bg-brand-blue' : 'border-slate-300'">
                                <i data-lucide="check" class="w-3.5 h-3.5 text-white" x-show="String(selectedProgram) === '{{ $prog->id }}'"></i>
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
                <p class="text-xs text-gray-500 mt-0.5 mb-0">How long will you be with us? ({{ $durationMin }}–{{ $durationMax }} days)</p>
                <div class="apply-chip-row" role="group" aria-label="Duration presets">
                    @foreach ($durationPresets as $preset)
                        <button type="button" class="apply-chip"
                                :class="String(durationDays) === '{{ $preset }}' ? 'is-on' : ''"
                                @click="setDuration({{ $preset }})">{{ $preset }} days</button>
                    @endforeach
                </div>
                <input required type="number" name="internship_duration_days"
                       min="{{ $durationMin }}" max="{{ $durationMax }}"
                       x-model="durationDays"
                       inputmode="numeric"
                       placeholder="Or type days, e.g. 45"
                       class="apply-field mt-3 sm:max-w-xs">
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

        <div class="apply-step" id="step-contact">
            <div class="apply-step-head">
                @if($isInternship)<span class="apply-step-num">2</span>@endif
                <div>
                    <h3 class="apply-step-title">{{ $isInternship ? 'Contact information' : 'Contact information' }}</h3>
                    <p class="apply-step-sub">We’ll reach you on WhatsApp</p>
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-semibold text-gray-700">Full name *</label>
                    <input required name="full_name" value="{{ old('full_name') }}" type="text" autocomplete="name"
                           class="apply-field" placeholder="As on your ID card">
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-700">Email *</label>
                    <input required name="email" value="{{ old('email') }}" type="email" autocomplete="email" inputmode="email"
                           class="apply-field" placeholder="you@example.com">
                </div>
                <div>
                    <label class="text-sm font-bold text-brand-blue">WhatsApp number *</label>
                    <p class="text-xs text-gray-500 mt-0.5">Used for all application status notifications.</p>
                    <div class="apply-phone-row">
                        <select name="country_code" class="apply-phone-cc" aria-label="Country code">
                            @foreach ($countryCodes as $code => $label)
                                <option value="{{ $code }}" @if(old('country_code', '+237') === $code) selected @endif>{{ $code }}</option>
                            @endforeach
                        </select>
                        <input required name="whatsapp_number" data-wa-phone="local"
                               value="{{ old('whatsapp_number') }}"
                               type="tel" inputmode="numeric" pattern="[0-9]*" placeholder="675321739"
                               autocomplete="tel-national"
                               class="apply-phone-local @error('whatsapp_number') border-red-500 @enderror">
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Enter digits only (e.g. 675321739).</p>
                    @error('whatsapp_number')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                @unless($isInternship)
                    <div>
                        <label class="text-sm font-semibold text-gray-700">Expected salary (optional)</label>
                        <input name="expected_salary" value="{{ old('expected_salary') }}" type="text" placeholder="e.g. 600,000 RWF" class="apply-field">
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
                            ['Custom', 'Custom'],
                        ] as [$value, $label])
                            <label :class="availability === '{{ $value }}' ? 'is-on' : ''">
                                <input type="radio" value="{{ $value }}" x-model="availability"
                                       @if(old('availability', 'Immediately') === $value) checked @endif>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div x-show="availability === 'Custom'" x-cloak x-transition class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5">
                    <label class="text-sm font-bold text-brand-blue">Available in (days)</label>
                    <p class="text-xs text-gray-500 mt-0.5 mb-0">Days from today until you can start.</p>
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
        <div class="apply-step" id="step-education">
            <div class="apply-step-head">
                <span class="apply-step-num">3</span>
                <div>
                    <h3 class="apply-step-title">Education</h3>
                    <p class="apply-step-sub">School details for your placement</p>
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-semibold text-gray-700">School / Institution *</label>
                    <input required name="school" value="{{ old('school') }}" type="text" autocomplete="organization"
                           placeholder="e.g. University of Bamenda" class="apply-field">
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-700">Level of study *</label>
                    <select required name="level_of_study" class="apply-field">
                        <option value="">Select level…</option>
                        @foreach (['Secondary / High School', 'Certificate', 'Diploma / HND', 'Bachelor’s degree', 'Master’s degree', 'PhD / Doctorate', 'Other'] as $level)
                            <option value="{{ $level }}" @if(old('level_of_study') === $level) selected @endif>{{ $level }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-700">Student status *</label>
                    <div class="apply-toggle" role="radiogroup">
                        <label :class="educationStatus === 'currently_studying' ? 'is-on' : ''">
                            <input type="radio" name="education_status" value="currently_studying" required
                                   x-model="educationStatus"
                                   @if(old('education_status', 'currently_studying') === 'currently_studying') checked @endif>
                            Currently studying
                        </label>
                        <label :class="educationStatus === 'graduated' ? 'is-on' : ''">
                            <input type="radio" name="education_status" value="graduated" required
                                   x-model="educationStatus"
                                   @if(old('education_status') === 'graduated') checked @endif>
                            Graduated
                        </label>
                    </div>
                </div>
                <div x-show="educationStatus === 'currently_studying'" x-cloak>
                    <label class="text-sm font-semibold text-gray-700">Academic-required internship? *</label>
                    <p class="text-xs text-gray-500 mt-0.5">Yes if your school requires this for graduation.</p>
                    <div class="apply-toggle" role="radiogroup">
                        <label :class="academicRequired === '1' ? 'is-on' : ''">
                            <input type="radio" name="is_academic_required" value="1"
                                   x-model="academicRequired"
                                   x-bind:disabled="educationStatus !== 'currently_studying'"
                                   @if(old('is_academic_required', '1') === '1') checked @endif>
                            Yes — school required
                        </label>
                        <label :class="academicRequired === '0' ? 'is-on' : ''">
                            <input type="radio" name="is_academic_required" value="0"
                                   x-model="academicRequired"
                                   x-bind:disabled="educationStatus !== 'currently_studying'"
                                   @if(old('is_academic_required', '1') === '0') checked @endif>
                            No — voluntary
                        </label>
                    </div>
                </div>
                <template x-if="educationStatus === 'graduated'">
                    <input type="hidden" name="is_academic_required" value="0">
                </template>
                <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2.5 mb-0 leading-relaxed"
                   x-show="educationStatus === 'currently_studying' && academicRequired === '1'" x-cloak>
                    An internship letter from your school is required in the Documents step.
                </p>
            </div>
        </div>

        <div class="apply-step" id="step-working-week">
            <div class="apply-step-head">
                <span class="apply-step-num">4</span>
                <div>
                    <h3 class="apply-step-title">Working Week</h3>
                    <p class="apply-step-sub">Your usual days and hours — required for daily tasks</p>
                </div>
            </div>
            @error('working_week')
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 mb-3">{{ $message }}</div>
            @enderror
            @include('beyond.apply.partials.working_week_fields', [
                'wwData' => old() ?: \App\Support\WorkingWeekForm::defaultData(),
                'formId' => 'apply-ww',
                'required' => true,
            ])
        </div>
        @endif

        <div class="apply-step" id="step-documents">
            <div class="apply-step-head">
                @if($isInternship)<span class="apply-step-num">5</span>@endif
                <div>
                    <h3 class="apply-step-title">Documents</h3>
                    <p class="apply-step-sub">{{ $isInternship ? 'Snap photos or attach files' : 'Upload your CV' }}</p>
                </div>
            </div>
            @unless($isInternship)
                <div>
                    <label class="text-sm font-semibold text-gray-700">Resume / CV (PDF, DOC, DOCX) *</label>
                    <input required name="cv" type="file" accept=".pdf,.doc,.docx"
                           class="w-full mt-1 text-sm text-gray-600 file:mr-3 file:py-2.5 file:px-4 file:rounded-md file:border-0 file:bg-brand-blue file:text-white file:font-semibold">
                </div>
            @else
                <div class="mb-3">
                    <label class="text-sm font-semibold text-gray-700">Resume / CV (optional)</label>
                    <input name="cv" type="file" accept=".pdf,.doc,.docx"
                           class="w-full mt-1 text-sm text-gray-600 file:mr-3 file:py-2.5 file:px-4 file:rounded-md file:border-0 file:bg-brand-blue file:text-white file:font-semibold">
                    <p class="text-xs text-gray-500 mt-1">Not required for internships.</p>
                </div>

                <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-3.5 sm:p-4 space-y-3">
                    <div>
                        <p class="text-sm font-bold text-emerald-900 m-0">Required internship documents</p>
                        <p class="text-xs text-emerald-800 mt-1 mb-0 leading-relaxed">Prefer <strong>Snap with camera</strong> on your phone. You can also attach a file.</p>
                    </div>

                    <div class="rounded-xl border border-emerald-300 bg-white p-3">
                        <p class="text-sm font-bold text-gray-800 m-0">National ID card *</p>
                        <p class="text-xs text-gray-600 mt-1 mb-0 leading-relaxed">Front (photo side) and back are both required.</p>
                        @foreach ([
                            ['student_id', 'ID card — Front', 'environment', 'Snap ID Front'],
                            ['student_id_back', 'ID card — Back', 'environment', 'Snap ID Back'],
                        ] as [$field, $label, $facing, $snapTitle])
                            <div class="apply-doc-card" data-apply-doc data-facing="{{ $facing }}" data-title="{{ $snapTitle }}">
                                <label class="text-sm font-semibold text-gray-700">{{ $label }} *</label>
                                <input type="file" name="{{ $field }}" data-doc-target accept="image/*,.pdf" class="sr-only" tabindex="-1">
                                <input type="file" data-doc-attach accept="image/*,.pdf" class="hidden" id="attach-{{ $field }}">
                                <div class="apply-doc-actions">
                                    <button type="button" data-doc-snap class="apply-doc-btn primary">
                                        <i data-lucide="camera" class="w-4 h-4"></i> Snap
                                    </button>
                                    <label for="attach-{{ $field }}" class="apply-doc-btn">
                                        <i data-lucide="paperclip" class="w-4 h-4"></i> Attach
                                    </label>
                                </div>
                                <p class="text-xs text-emerald-700 mt-2 min-h-[1rem] mb-0" data-doc-status>No file yet</p>
                                <img data-doc-preview alt="{{ $label }} preview" class="hidden mt-2 max-h-36 w-full rounded-lg border border-emerald-200 object-cover">
                            </div>
                        @endforeach
                    </div>

                    <div class="apply-doc-card" data-apply-doc data-facing="environment" data-title="Snap Internship Letter">
                        <label class="text-sm font-semibold text-gray-700">
                            Internship letter
                            <span x-text="(educationStatus === 'currently_studying' && academicRequired === '1') ? '*' : '(optional)'"></span>
                        </label>
                        <input type="file" name="internship_letter" data-doc-target accept="image/*,.pdf" class="sr-only" tabindex="-1">
                        <input type="file" data-doc-attach accept="image/*,.pdf" class="hidden" id="attach-internship_letter">
                        <div class="apply-doc-actions">
                            <button type="button" data-doc-snap class="apply-doc-btn primary">
                                <i data-lucide="camera" class="w-4 h-4"></i> Snap
                            </button>
                            <label for="attach-internship_letter" class="apply-doc-btn">
                                <i data-lucide="paperclip" class="w-4 h-4"></i> Attach
                            </label>
                        </div>
                        <p class="text-xs text-emerald-700 mt-2 min-h-[1rem] mb-0" data-doc-status>No file yet</p>
                        <img data-doc-preview alt="Internship Letter preview" class="hidden mt-2 max-h-36 w-full rounded-lg border border-emerald-200 object-cover">
                    </div>

                    @foreach ([
                        ['selfie', 'Selfie / Photo', 'user', 'Snap Selfie'],
                    ] as [$field, $label, $facing, $snapTitle])
                        <div class="apply-doc-card" data-apply-doc data-facing="{{ $facing }}" data-title="{{ $snapTitle }}">
                            <label class="text-sm font-semibold text-gray-700">{{ $label }} *</label>
                            <input type="file" name="{{ $field }}" data-doc-target accept="image/*{{ $field !== 'selfie' ? ',.pdf' : '' }}" class="sr-only" tabindex="-1">
                            <input type="file" data-doc-attach accept="image/*{{ $field !== 'selfie' ? ',.pdf' : '' }}" class="hidden" id="attach-{{ $field }}">
                            <div class="apply-doc-actions">
                                <button type="button" data-doc-snap class="apply-doc-btn primary">
                                    <i data-lucide="camera" class="w-4 h-4"></i> Snap selfie
                                </button>
                                <label for="attach-{{ $field }}" class="apply-doc-btn">
                                    <i data-lucide="paperclip" class="w-4 h-4"></i> Attach
                                </label>
                            </div>
                            <p class="text-xs text-emerald-700 mt-2 min-h-[1rem] mb-0" data-doc-status>No file yet</p>
                            <img data-doc-preview alt="{{ $label }} preview" class="hidden mt-2 max-h-36 w-full rounded-lg border border-emerald-200 object-cover">
                            <p class="text-xs text-gray-500 mt-1.5 mb-0">Front camera opens first; use Flip if needed.</p>
                        </div>
                    @endforeach

                    <div class="apply-doc-card border-amber-200">
                        <label class="text-sm font-semibold text-gray-700">Signature *</label>
                        <p class="text-xs text-gray-500 mt-0.5 mb-2">Sign with your finger in the box below.</p>
                        <canvas id="apply-signature-pad" class="w-full border-2 border-dashed border-brand-gold rounded-xl bg-white" style="height:160px;touch-action:none;"></canvas>
                        <input type="hidden" name="signature_image" id="signature_image">
                        <button type="button" id="clear-signature" class="mt-2 min-h-[2.5rem] text-sm font-bold text-brand-blue underline">Clear signature</button>
                    </div>

                    <label class="flex items-start gap-3 text-sm text-gray-700 leading-relaxed bg-white border border-emerald-200 rounded-xl p-3">
                        <input type="checkbox" name="agreement_accepted" value="1" class="mt-1 w-5 h-5 shrink-0 accent-[#0b3f90]" required>
                        <span>I confirm my documents are accurate and I understand this internship is unpaid with required timesheets.</span>
                    </label>
                </div>
            @endunless
        </div>

        <div class="apply-step" id="step-cover">
            <div class="apply-step-head">
                @if($isInternship)<span class="apply-step-num">6</span>@endif
                <div>
                    <h3 class="apply-step-title">Cover letter <span class="normal-case font-semibold text-gray-500">(optional)</span></h3>
                    <p class="apply-step-sub">A short note helps us know you</p>
                </div>
            </div>
            <textarea name="cover_letter" rows="4" placeholder="Tell us why you're a great fit..."
                      class="apply-field mt-0">{{ old('cover_letter') }}</textarea>
        </div>

        <div class="apply-inline-submit space-y-2">
            <button type="submit" class="w-full bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-extrabold py-4 rounded-xl flex items-center justify-center gap-2 text-base shadow-md min-h-[3.25rem]">
                <i data-lucide="send" class="w-5 h-5"></i> Submit application
            </button>
            <p class="text-xs text-gray-500 text-center mb-0">You will be notified on WhatsApp when your application is under review.</p>
        </div>
    </form>
</div>

@if($isInternship)
<div class="apply-sticky-submit">
    <button type="submit" form="apply-form" class="w-full bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-extrabold py-3.5 rounded-xl flex items-center justify-center gap-2 text-base shadow-md min-h-[3.1rem]">
        <i data-lucide="send" class="w-5 h-5"></i> Submit application
    </button>
</div>
@endif
