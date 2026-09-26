@extends(auth()->user()->hasRole('SUPER_ADMIN') ? 'layouts.admin' : 'layouts.role')

@section('page-title','Assign New Work')
@section('page-subtitle','Client Google Sheet remains the source of truth')

@section('content')
<div class="mx-auto max-w-6xl">
    @if(session('assignment_warnings'))
        <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-950">
            <h2 class="font-bold">Assignment warning</h2>
            <p class="mt-1 text-sm">Warnings do not block assignment. Review them before using Assign Anyway.</p>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                @foreach(session('assignment_warnings') as $warning)<li>{{ $warning['message'] }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="mb-5 flex flex-wrap gap-2">
        @foreach($workTypes as $type)
            <a href="{{ route('work.create',['type'=>$type->value]) }}"
               class="rounded-lg px-4 py-2 text-sm font-semibold {{ $workType===$type ? 'bg-slate-900 text-white':'border border-slate-300 bg-white' }}">
                {{ $type->label() }}
            </a>
        @endforeach
    </div>

    <form method="POST" action="{{ route('work.store') }}" id="work-form">
        @csrf
        <input type="hidden" name="work_type" value="{{ $workType->value }}">

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-bold">Work setup</h2>
                <div class="mt-5 grid gap-4">
                    <div>
                        <label class="text-sm font-semibold">Client *</label>
                        <select name="client_id" required class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5">
                            <option value="">Select active client</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" @selected((string)old('client_id',$preselectedClientId)===(string)$client->id)>
                                    {{ $client->name }} — {{ $client->client_code }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">The current Sheet URL is snapshotted at creation.</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold">Priority *</label>
                        <select name="priority" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5">
                            @foreach($priorities as $priority)
                                <option value="{{ $priority->value }}" @selected(old('priority','NORMAL')===$priority->value)>{{ $priority->value }}</option>
                            @endforeach
                        </select>
                    </div>

                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-3">
                        <input type="checkbox" name="proof_required" value="1" @checked(old('proof_required'))>
                        <span><strong class="block text-sm">Proof required</strong><span class="text-xs text-slate-500">The flag is stored now; file upload activates later.</span></span>
                    </label>

                    @if($workType===\App\Enums\WorkType::SOCIAL)
                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div><p class="font-semibold">Social Activities *</p><p class="text-xs text-slate-500">Choose activities or Full Activity.</p></div>
                                <label class="flex items-center gap-2 text-sm font-semibold"><input id="full-activity" type="checkbox" name="full_activity" value="1" @checked(old('full_activity'))> Full Activity</label>
                            </div>
                            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                @foreach($activities as $activity)
                                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                        <input class="activity-checkbox" type="checkbox" name="activity_type_ids[]" value="{{ $activity->id }}" data-full="{{ $activity->is_full_activity_default ? '1':'0' }}" @checked(in_array($activity->id,old('activity_type_ids',[])))>
                                        {{ $activity->name }} @if($activity->is_full_activity_default)<span class="text-xs text-slate-400">Full</span>@endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($workType===\App\Enums\WorkType::CUSTOM)
                        <div>
                            <label class="text-sm font-semibold">Custom Job Type</label>
                            <select id="custom-job-type" name="custom_job_type_id" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5">
                                <option value="">Other / Custom Job</option>
                                @foreach($customJobTypes as $type)
                                    <option value="{{ $type->id }}" data-instruction="{{ e($type->default_instruction??'') }}" @selected((string)old('custom_job_type_id')===(string)$type->id)>{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-semibold">Custom Title</label>
                            <input name="custom_title" value="{{ old('custom_title') }}" placeholder="Required for Other / Custom Job" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5">
                        </div>
                        <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-3">
                            <input id="special-custom" type="checkbox" name="special_custom_split" value="1" @checked(old('special_custom_split'))>
                            <span><strong class="block text-sm">Special Custom Job — split between employees</strong><span class="text-xs text-slate-500">Normal Custom Job uses one employee.</span></span>
                        </label>
                    @endif

                    <div>
                        <label class="text-sm font-semibold">{{ $workType===\App\Enums\WorkType::CUSTOM ? 'What needs to be done? *':'Instruction' }}</label>
                        <textarea id="instruction" name="instruction" rows="5" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5" {{ $workType===\App\Enums\WorkType::CUSTOM ? 'required':'' }}>{{ old('instruction') }}</textarea>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div><h2 class="font-bold">Employee assignments</h2><p class="mt-1 text-xs text-slate-500">Each section/employee becomes an independent assignment.</p></div>
                    <button type="button" id="add-assignment" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">+ Add</button>
                </div>
                <div id="assignment-list" class="mt-5 space-y-4"></div>
                <template id="assignment-template">
                    <div class="assignment-row rounded-xl border border-slate-200 p-4">
                        <div class="flex items-center justify-between"><p class="assignment-title font-semibold">Assignment</p><button type="button" class="remove-assignment text-sm font-semibold text-red-700">Remove</button></div>
                        <div class="mt-4 grid gap-3">
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Employee *</label>
                                <select data-field="employee_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5">
                                    <option value="">Select employee</option>
                                    @foreach($employees as $employee)
                                        @php $caps=$employee->capabilities->pluck('capability')->map(fn($c)=>$c->value)->join(', '); @endphp
                                        <option value="{{ $employee->id }}">{{ $employee->name }} — {{ $caps ?: 'No capability' }} — Active work {{ $employee->active_workload_count }}</option>
                                    @endforeach
                                </select>
                            </div>

                            @if($workType!==\App\Enums\WorkType::CUSTOM)
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Section *</label>
                                    <select data-field="section_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5">
                                        <option value="">Select section</option>
                                        @foreach($sections as $section)<option value="{{ $section->id }}">{{ $section->name }}</option>@endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Scope *</label>
                                    <select data-field="scope_type" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5">
                                        <option value="FULL_SECTION">Full Section</option>
                                        <option value="SPECIFIC_SITES">Specific Sites / Partial Section</option>
                                    </select>
                                </div>
                            @endif

                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $workType===\App\Enums\WorkType::CUSTOM ? 'Scope / part description':'Site list / scope' }}</label>
                                <textarea data-field="scope_text" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Assigned Count</label>
                                <input data-field="assigned_count" type="number" min="1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="{{ $workType===\App\Enums\WorkType::CUSTOM ? 'Optional':'Sites/accounts' }}">
                            </div>
                        </div>
                    </div>
                </template>
            </section>
        </div>

        @if(session('assignment_warnings'))
            <label class="mt-5 flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4">
                <input type="checkbox" name="confirm_warnings" value="1" class="mt-1">
                <span class="text-sm font-semibold">I reviewed the warnings and want to Assign Anyway.</span>
            </label>
        @endif

        <div class="mt-5 flex gap-3">
            <button class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white">{{ session('assignment_warnings') ? 'Assign Anyway':'Create Work Order' }}</button>
            <a href="{{ route('work.index') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold">Cancel</a>
        </div>
    </form>
</div>

<script>
(() => {
    const list=document.getElementById('assignment-list'), template=document.getElementById('assignment-template'), add=document.getElementById('add-assignment');
    const oldAssignments=@json(old('assignments',[]));
    function renumber(){
        [...list.querySelectorAll('.assignment-row')].forEach((row,index)=>{
            row.querySelector('.assignment-title').textContent=`Assignment ${index+1}`;
            row.querySelectorAll('[data-field]').forEach(input=>input.name=`assignments[${index}][${input.dataset.field}]`);
        });
    }
    function addAssignment(values={}){
        const node=template.content.cloneNode(true), row=node.querySelector('.assignment-row');
        row.querySelectorAll('[data-field]').forEach(input=>{const field=input.dataset.field;if(values[field]!==undefined&&values[field]!==null)input.value=values[field];});
        row.querySelector('.remove-assignment').addEventListener('click',()=>{if(list.querySelectorAll('.assignment-row').length===1)return;row.remove();renumber();});
        list.appendChild(row);renumber();
    }
    add.addEventListener('click',()=>addAssignment());
    oldAssignments.length?oldAssignments.forEach(addAssignment):addAssignment();

    const full=document.getElementById('full-activity');
    if(full) full.addEventListener('change',()=>{if(full.checked)document.querySelectorAll('.activity-checkbox').forEach(box=>box.checked=box.dataset.full==='1');});

    const job=document.getElementById('custom-job-type'), instruction=document.getElementById('instruction');
    if(job&&instruction) job.addEventListener('change',()=>{const suggestion=job.options[job.selectedIndex]?.dataset?.instruction||'';if(suggestion&&instruction.value.trim()==='')instruction.value=suggestion;});

    const special=document.getElementById('special-custom');
    if(special) add.addEventListener('click',()=>{if(list.querySelectorAll('.assignment-row').length>1)special.checked=true;});
})();
</script>
@endsection
