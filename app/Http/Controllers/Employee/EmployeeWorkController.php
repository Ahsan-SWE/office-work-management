<?php
namespace App\Http\Controllers\Employee;
use App\Actions\Work\StartAssignmentAction;
use App\Actions\Work\UpdateAssignmentProgressAction;
use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeWorkController extends Controller {
    public function index(Request $request): View {
        $assignments=Assignment::query()
            ->with(['workOrder.client:id,client_code,name','section:id,name'])
            ->where('employee_id',$request->user()->id)
            ->whereIn('status',[AssignmentStatus::PENDING->value,AssignmentStatus::ONGOING->value,AssignmentStatus::REWORK->value])
            ->orderByRaw("CASE status WHEN 'REWORK' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE priority WHEN 'URGENT' THEN 1 WHEN 'HIGH' THEN 2 ELSE 3 END")
            ->orderBy('assigned_at')->get();
        return view('employee.work.index',compact('assignments'));
    }
    public function show(Request $request,Assignment $assignment): View {
        $this->owner($request,$assignment);
        $assignment->load(['workOrder.client.currentTier','workOrder.creator:id,name,email','section:id,name','progressLogs.user:id,name,email']);
        return view('employee.work.show',compact('assignment'));
    }
    public function start(Request $request,Assignment $assignment,StartAssignmentAction $action): RedirectResponse {
        $this->owner($request,$assignment);
        try{$action->handle($assignment,$request->user()->id);}
        catch(DomainException $e){return back()->withErrors(['assignment'=>$e->getMessage()]);}
        return back()->with('success','Work started. Your Team Leader has been notified.');
    }
    public function progress(Request $request,Assignment $assignment,UpdateAssignmentProgressAction $action): RedirectResponse {
        $this->owner($request,$assignment);
        $data=$request->validate(['completed_count'=>['nullable','integer','min:0','max:1000000'],'note'=>['nullable','string','max:10000']]);
        try{
            $action->handle($assignment,$request->user()->id,
                array_key_exists('completed_count',$data)&&$data['completed_count']!==null?(int)$data['completed_count']:null,
                $data['note']??null);
        } catch(DomainException $e){return back()->withErrors(['progress'=>$e->getMessage()]);}
        return back()->with('success','Progress updated.');
    }
    private function owner(Request $request,Assignment $assignment): void { abort_unless($assignment->employee_id===$request->user()->id,403); }
}
