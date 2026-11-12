@php
    $durationMin = \App\Support\PaidInternship::minDays();
    $durationMax = \App\Support\PaidInternship::maxDays();
    $durationPresets = array_keys(\App\Support\PaidInternship::anchors());
    $feeAnchorJs = '{'.implode(',', array_map(function ($days, $amount) {
        return (int) $days.':'.(int) $amount;
    }, array_keys(\App\Support\PaidInternship::anchors()), \App\Support\PaidInternship::anchors())).'}';
    $applySection = 1;
    if ($isInternship && $errors->any()) {
        if ($errors->has('student_id') || $errors->has('student_id_back') || $errors->has('selfie') || $errors->has('signature_image') || $errors->has('agreement_accepted')) {
            $applySection = 5;
        } elseif ($errors->has('working_week')) {
            $applySection = 4;
        } elseif ($errors->has('school') || $errors->has('level_of_study') || $errors->has('education_status')) {
            $applySection = 3;
        } elseif ($errors->has('whatsapp_number') || $errors->has('full_name') || $errors->has('availability')) {
            $applySection = 2;
        }
    }
@endphp
<div class="apply-panel overflow-hidden">
    <div class="bg-gradient-to-r from-brand-blue to-[#0a3578] text-white px-4 sm:px-7 py-5 sm:py-6">
        <p class="text-brand-gold text-xs font-bold uppercase tracking-wider m-0">Application form</p>
        <h2 class="text-xl sm:text-2xl font-extrabold m-0 mt-1">{{ $isInternship ? 'Paid internship' : 'Job application' }}</h2>
        <p class="text-blue-100 text-sm m-0 mt-1 leading-relaxed">
            @if($isInternship)
                15 days is 15,000 XAF. 60 days is 25,000 XAF. Longer stays cost less per day. Pay by MoMo after you submit. Open through 30 November 2026.
            @else
                Fill in your details and submit your documents.
            @endif
        </p>
    </div>

    @if ($errors->any())
        <div class="mx-4 sm:mx-7 mt-4 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
            <p class="font-bold m-0 mb-1">Please fix the following:</p>
            <ul class="list-disc pl-5 space-y-1 mb-0">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('apply.store', $job->id) }}" enctype="multipart/form-data" @if($isInternship) novalidate @endif
          class="p-4 sm:p-7 space-y-6 sm:space-y-7 apply-form-pad" id="apply-form"
          x-data="{
              availability: '{{ old('availability', 'Immediately') }}',
              educationStatus: '{{ old('education_status', 'currently_studying') }}',
              academicRequired: '{{ old('is_academic_required', '1') }}',
              deferInternshipLetter: {{ old('defer_internship_letter') === '1' ? 'true' : 'false' }},
              deferWorkerProof: {{ old('defer_worker_proof') === '1' ? 'true' : 'false' }},
              selectedProgram: '{{ old('internship_program_id', '') }}',
              durationDays: '{{ old('internship_duration_days', '') }}',
              section: {{ (int) $applySection }},
              sectionTitle() {
                  return ['', 'Choose a program', 'Contact information', 'Education', 'Working week', 'ID, selfie and signature'][this.section] || '';
              },
              go(next) {
                  if (next > this.section && !this.sectionReady()) return;
                  this.section = next;
                  var top = document.getElementById('apply-section');
                  if (top) top.scrollIntoView({ behavior: 'smooth', block: 'start' });
              },
              sectionReady() {
                  var form = document.getElementById('apply-form');
                  if (this.section === 1) {
                      if (!this.selectedProgram) { alert('Choose a program.'); return false; }
                      var days = parseInt(this.durationDays, 10);
                      if (!days || days < {{ (int) $durationMin }} || days > {{ (int) $durationMax }}) {
                          alert('Choose between {{ (int) $durationMin }} and {{ (int) $durationMax }} days.');
                          return false;
                      }
                  }
                  if (this.section === 2) {
                      var phone = ((form.querySelector('[name=whatsapp_number]') || {}).value || '').replace(/\D/g, '');
                      var person = ((form.querySelector('[name=full_name]') || {}).value || '').trim();
                      if (phone.length < 8) { alert('Enter your WhatsApp number.'); return false; }
                      if (!person) { alert('Enter your full name.'); return false; }
                  }
                  if (this.section === 3 && !this.isWorker) {
                      var school = ((form.querySelector('[name=school]') || {}).value || '').trim();
                      var level = (form.querySelector('[name=level_of_study]') || {}).value || '';
                      if (!school || !level) { alert('Enter your school and level of study.'); return false; }
                  }
                  if (this.section === 4) {
                      var on = form.querySelectorAll('.ww-day-toggle:checked');
                      if (!on.length) { alert('Select at least one working day.'); return false; }
                  }
                  return true;
              },
              feeAnchors: {!! $feeAnchorJs !!},
              setDuration(d) { this.durationDays = String(d); },
              feeAmount() {
                  const days = parseInt(this.durationDays, 10);
                  const keys = Object.keys(this.feeAnchors).map(function (k) { return parseInt(k, 10); }).sort(function (a, b) { return a - b; });
                  if (!days || !keys.length) return 0;
                  if (days <= keys[0]) return this.feeAnchors[keys[0]];
                  const last = keys[keys.length - 1];
                  if (days >= last) return this.feeAnchors[last];
                  for (let i = 0; i < keys.length - 1; i++) {
                      const from = keys[i];
                      const to = keys[i + 1];
                      if (days >= from && days <= to) {
                          const ratio = (days - from) / (to - from);
                          return Math.round(this.feeAnchors[from] + ((this.feeAnchors[to] - this.feeAnchors[from]) * ratio));
                      }
                  }
                  return this.feeAnchors[last];
              },
              feeText() {
                  const amount = this.feeAmount();
                  if (!amount) return 'Choose the number of days';
                  return amount.toLocaleString() + ' XAF';
              },
              get isWorker() { return this.educationStatus === 'not_a_student'; }
          }">
        @csrf
        @if($isInternship)
            <div class="apply-progress -mx-4 sm:-mx-7 -mt-4 sm:-mt-7 mb-2">
                <div class="apply-progress-track"><div class="apply-progress-bar" :style="'width:' + Math.round((section / 5) * 100) + '%'"></div></div>
                <div class="apply-progress-meta">
                    <span x-text="sectionTitle()"></span>
                    <span x-text="'Section ' + section + ' of 5'"></span>
                </div>
            </div>
        @endif

        @if($isInternship && $offerPrograms->isNotEmpty())
        <div class="apply-step rounded-2xl border-2 border-brand-blue/20 bg-gradient-to-b from-[#eef4ff] to-white p-3.5 sm:p-5" id="step-program" x-show="section === 1" x-cloak>
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
                        <input type="radio" name="internship_program_id" value="{{ $prog->id }}" :required="section === 1"
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
                <label class="text-sm font-bold text-brand-blue">Paid internship duration (days) *</label>
                <p class="text-xs text-gray-500 mt-0.5 mb-0">{{ $durationMin }}–{{ $durationMax }} days. The fee falls per day as the stay gets longer.</p>
                <div class="apply-chip-row" role="group" aria-label="Duration presets">
                    @foreach ($durationPresets as $preset)
                        <button type="button" class="apply-chip"
                                :class="String(durationDays) === '{{ $preset }}' ? 'is-on' : ''"
                                @click="setDuration({{ $preset }})">{{ $preset }} days · {{ \App\Support\PaidInternship::money(\App\Support\PaidInternship::price($preset)) }}</button>
                    @endforeach
                </div>
                <input type="number" name="internship_duration_days" :required="section === 1"
                       min="{{ $durationMin }}" max="{{ $durationMax }}"
                       x-model="durationDays"
                       inputmode="numeric"
                       placeholder="Or type days, e.g. 45"
                       class="apply-field mt-3 sm:max-w-xs">
                <p class="text-sm font-semibold text-brand-blue mt-3 mb-0">MoMo fee: <span x-text="feeText()"></span></p>
                @error('internship_duration_days')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <button type="button" class="mt-5 w-full bg-brand-blue text-white font-extrabold py-3.5 rounded-xl" @click="go(2)">Continue</button>
        </div>
        @elseif($isInternship)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                No internship programs are published yet. Please contact the administrator before applying.
            </div>
        @endif

        <div class="apply-step" id="step-contact" @if($isInternship) x-show="section === 2" x-cloak @endif>
            <div class="apply-step-head">
                @if($isInternship)<span class="apply-step-num">2</span>@endif
                <div>
                    <h3 class="apply-step-title">{{ $isInternship ? 'Contact information' : 'Contact information' }}</h3>
                    <p class="apply-step-sub">We’ll reach you on WhatsApp</p>
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-bold text-brand-blue">WhatsApp number *</label>
                    <p class="text-xs text-gray-500 mt-0.5">Enter your number first. If it is on file — even if you do not have an account yet — your name will appear.</p>
                    <div class="apply-phone-row">
                        <select name="country_code" id="apply-country" class="apply-phone-cc" aria-label="Country code">
                            @foreach ($countryCodes as $code => $label)
                                <option value="{{ $code }}" @if(old('country_code', '+237') === $code) selected @endif>{{ $code }}</option>
                            @endforeach
                        </select>
                        <input name="whatsapp_number" data-wa-phone="local" id="apply-phone" @if($isInternship) :required="section === 2" @else required @endif
                               value="{{ old('whatsapp_number') }}"
                               type="tel" inputmode="numeric" pattern="[0-9]*" placeholder="675321739"
                               autocomplete="tel-national"
                               class="apply-phone-local @error('whatsapp_number') border-red-500 @enderror">
                    </div>
                    <p id="apply-phone-status" class="text-xs mt-1" style="display:none;"></p>
                    <p class="text-xs text-gray-500 mt-1">Enter digits only (e.g. 675321739).</p>
                    @error('whatsapp_number')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-gray-700">Full name *</label>
                    <input name="full_name" id="apply-name" value="{{ old('full_name') }}" type="text" autocomplete="name"
                           class="apply-field" placeholder="As on your ID card" @if($isInternship) :required="section === 2" @else required @endif>
                </div>
                @unless($isInternship)
                <div>
                    <label class="text-sm font-semibold text-gray-700">Email *</label>
                    <input required name="email" id="apply-email" value="{{ old('email') }}" type="email" autocomplete="email" inputmode="email"
                           class="apply-field" placeholder="you@example.com">
                </div>
                @endunless
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
            @if($isInternship)
                <div class="flex gap-2 mt-5">
                    <button type="button" class="px-4 py-3.5 rounded-xl border border-slate-300 font-bold" @click="go(1)">Back</button>
                    <button type="button" class="flex-1 bg-brand-blue text-white font-extrabold py-3.5 rounded-xl" @click="go(3)">Continue</button>
                </div>
            @endif
        </div>

        @if($isInternship)
        <div class="apply-step" id="step-education" x-show="section === 3" x-cloak>
            <div class="apply-step-head">
                <span class="apply-step-num">3</span>
                <div>
                    <h3 class="apply-step-title">Education</h3>
                    <p class="apply-step-sub">School details for your placement</p>
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-semibold text-gray-700">Applicant type *</label>
                    <div class="apply-toggle" role="radiogroup" style="flex-wrap:wrap;">
                        <label :class="educationStatus === 'currently_studying' ? 'is-on' : ''">
                            <input type="radio" name="education_status" value="currently_studying" :required="section === 3"
                                   x-model="educationStatus"
                                   @if(old('education_status', 'currently_studying') === 'currently_studying') checked @endif>
                            Currently studying
                        </label>
                        <label :class="educationStatus === 'graduated' ? 'is-on' : ''">
                            <input type="radio" name="education_status" value="graduated" :required="section === 3"
                                   x-model="educationStatus"
                                   @if(old('education_status') === 'graduated') checked @endif>
                            Graduated
                        </label>
                        <label :class="educationStatus === 'not_a_student' ? 'is-on' : ''">
                            <input type="radio" name="education_status" value="not_a_student" :required="section === 3"
                                   x-model="educationStatus"
                                   @if(old('education_status') === 'not_a_student') checked @endif>
                            Not a student
                        </label>
                    </div>
                    <p class="text-xs text-gray-500 mt-1 mb-0" x-show="isWorker" x-cloak>
                        Workers can apply. The next documents are an ID or passport, a selfie, and a signature.
                    </p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-gray-700">
                        <span x-show="!isWorker">School / Institution *</span>
                        <span x-show="isWorker" x-cloak>Organisation / Employer <span class="font-normal text-gray-500">(optional)</span></span>
                    </label>
                    <input name="school" value="{{ old('school') }}" type="text" autocomplete="organization"
                           class="apply-field"
                           x-bind:required="section === 3 && !isWorker"
                           x-bind:placeholder="isWorker ? 'e.g. Current workplace' : 'e.g. University of Bamenda'">
                </div>
                <div x-show="!isWorker" x-cloak>
                    <label class="text-sm font-semibold text-gray-700">Level of study *</label>
                    <select name="level_of_study" class="apply-field" x-bind:required="section === 3 && !isWorker">
                        <option value="">Select level…</option>
                        @foreach (['Secondary / High School', 'Certificate', 'Diploma / HND', 'Bachelor’s degree', 'Master’s degree', 'PhD / Doctorate', 'Other'] as $level)
                            <option value="{{ $level }}" @if(old('level_of_study') === $level) selected @endif>{{ $level }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="is_academic_required" value="0">
            </div>
            <div class="flex gap-2 mt-5">
                <button type="button" class="px-4 py-3.5 rounded-xl border border-slate-300 font-bold" @click="go(2)">Back</button>
                <button type="button" class="flex-1 bg-brand-blue text-white font-extrabold py-3.5 rounded-xl" @click="go(4)">Continue</button>
            </div>
        </div>

        <div class="apply-step" id="step-working-week" x-show="section === 4" x-cloak>
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
            <div class="flex gap-2 mt-5">
                <button type="button" class="px-4 py-3.5 rounded-xl border border-slate-300 font-bold" @click="go(3)">Back</button>
                <button type="button" class="flex-1 bg-brand-blue text-white font-extrabold py-3.5 rounded-xl" @click="go(5)">Continue</button>
            </div>
        </div>
        @endif

        <div class="apply-step" id="step-documents" @if($isInternship) x-show="section === 5" x-cloak @endif>
            <div class="apply-step-head">
                @if($isInternship)<span class="apply-step-num">5</span>@endif
                <div>
                    <h3 class="apply-step-title">{{ $isInternship ? 'ID, selfie and signature' : 'Documents' }}</h3>
                    <p class="apply-step-sub">{{ $isInternship ? 'Scan, attach, or continue on another device' : 'Upload your CV' }}</p>
                </div>
            </div>
            @unless($isInternship)
                <div>
                    <label class="text-sm font-semibold text-gray-700">Resume / CV (PDF, DOC, DOCX) *</label>
                    <input required name="cv" type="file" accept=".pdf,.doc,.docx"
                           class="w-full mt-1 text-sm text-gray-600 file:mr-3 file:py-2.5 file:px-4 file:rounded-md file:border-0 file:bg-brand-blue file:text-white file:font-semibold">
                </div>
            @else
                <input type="hidden" name="apply_handoff_token" id="apply-handoff-token" value="">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-3.5 sm:p-4 space-y-3">
                    <div>
                        <p class="text-sm font-bold text-emerald-900 m-0">ID or passport, selfie, and signature</p>
                        <p class="text-xs text-emerald-800 mt-1 mb-0 leading-relaxed">Scan with this device, attach a photo, or continue on another device if this one is not a phone.</p>
                    </div>
                    @foreach ([
                        ['student_id', 'id_front', 'ID or passport — front', 'environment', 'Scan the front'],
                        ['student_id_back', 'id_back', 'ID or passport — back', 'environment', 'Scan the back'],
                        ['selfie', 'selfie', 'Selfie', 'user', 'Take a selfie'],
                    ] as [$field, $slot, $label, $facing, $snapTitle])
                        <div class="apply-doc-card" data-apply-doc data-facing="{{ $facing }}" data-title="{{ $snapTitle }}" data-handoff-slot="{{ $slot }}">
                            <label class="text-sm font-semibold text-gray-700">{{ $label }} *</label>
                            <input type="file" name="{{ $field }}" data-doc-target accept="image/jpeg,image/png,image/webp" class="sr-only" tabindex="-1">
                            <input type="file" data-doc-attach accept="image/jpeg,image/png,image/webp" class="hidden" id="attach-{{ $field }}">
                            <div class="apply-doc-actions">
                                <button type="button" data-doc-snap class="apply-doc-btn primary">
                                    <i data-lucide="camera" class="w-4 h-4"></i> Scan
                                </button>
                                <label for="attach-{{ $field }}" class="apply-doc-btn">
                                    <i data-lucide="paperclip" class="w-4 h-4"></i> Attach
                                </label>
                                <button type="button" class="apply-doc-btn" data-handoff="{{ $slot }}">Continue on another device</button>
                            </div>
                            <div class="apply-qr mt-3 hidden" data-qr-slot="{{ $slot }}"></div>
                            <p class="text-xs text-emerald-700 mt-2 min-h-[1rem] mb-0" data-doc-status>No file yet</p>
                            <img data-doc-preview alt="{{ $label }} preview" class="hidden mt-2 max-h-36 w-full rounded-lg border border-emerald-200 object-cover">
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
                        <span>I confirm my documents are accurate. I understand this is a paid internship, I will pay the MoMo fee for the days I chose, and timesheets are required.</span>
                    </label>
                </div>
            @endunless
        </div>

        <div class="apply-step" id="step-cover" @if($isInternship) x-show="section === 5" x-cloak @endif>
            <div class="apply-step-head">
                @if($isInternship)<span class="apply-step-num">5</span>@endif
                <div>
                    <h3 class="apply-step-title">Cover letter <span class="normal-case font-semibold text-gray-500">(optional)</span></h3>
                    <p class="apply-step-sub">A short note helps us know you</p>
                </div>
            </div>
            <textarea name="cover_letter" rows="4" placeholder="Tell us why you're a great fit..."
                      class="apply-field mt-0">{{ old('cover_letter') }}</textarea>
        </div>

        <div class="apply-inline-submit space-y-2" @if($isInternship) x-show="section === 5" x-cloak @endif>
            @if($isInternship)
                <button type="button" class="w-full border border-slate-300 font-bold py-3 rounded-xl" @click="go(4)">Back to working week</button>
            @endif
            <button type="submit" class="w-full bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-extrabold py-4 rounded-xl flex items-center justify-center gap-2 text-base shadow-md min-h-[3.25rem]">
                <i data-lucide="send" class="w-5 h-5"></i> Submit application
            </button>
            <p class="text-xs text-gray-500 text-center mb-0">You will be notified on WhatsApp when your application is under review.</p>
        </div>
    </form>
</div>


@push('scripts')
<script src="{{ asset('public/js/phone-name-lookup.js') }}"></script>
<script>
if (window.attachPhoneNameLookup) {
    attachPhoneNameLookup({
        url: @json(route('directory.phone-lookup')),
        phone: '#apply-phone',
        code: '#apply-country',
        name: '#apply-name',
        email: document.getElementById('apply-email') ? '#apply-email' : '',
        status: '#apply-phone-status'
    });
}
</script>
@endpush
