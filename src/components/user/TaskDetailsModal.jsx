import React, { useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Slider } from '@/components/ui/slider';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { format } from 'date-fns';
import { Loader2, Calendar, AlertCircle, CheckCircle, Clock, Paperclip, X, Play } from 'lucide-react';
import { updateTaskProgress, completeTaskAssignment, uploadTaskAttachment, acceptTaskAssignment } from '@/services/taskService';
import { useToast } from '@/components/ui/use-toast';
import SignaturePadModal from '@/components/SignaturePadModal';
import { getStatusColor, getPriorityColor, getStatusIcon } from '@/components/admin/TaskDashboardCard';

const TaskDetailsModal = ({ isOpen, onClose, task, onTaskUpdated }) => {
  const [progress, setProgress] = useState(task?.my_progress || 0);
  const [comment, setComment] = useState('');
  const [files, setFiles] = useState([]);
  const [uploading, setUploading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [signatureOpen, setSignatureOpen] = useState(false);
  const { toast } = useToast();

  // Reset state when task changes
  React.useEffect(() => {
    if (task) {
      setProgress(task.my_progress || 0);
      setComment('');
      setFiles([]);
    }
  }, [task]);

  if (!task) return null;

  const handleFileChange = async (e) => {
    const selectedFiles = Array.from(e.target.files);
    if (!selectedFiles.length) return;

    setUploading(true);
    const newAttachments = [];
    for (const file of selectedFiles) {
      const res = await uploadTaskAttachment(file);
      if (res.success) {
        newAttachments.push({ name: res.name, url: res.url });
      } else {
        toast({ title: "Upload failed", description: res.error, variant: "destructive" });
      }
    }
    setFiles(prev => [...prev, ...newAttachments]);
    setUploading(false);
  };

  const handleUpdate = async () => {
    setSaving(true);
    const progressVal = typeof progress === 'number' ? progress : progress[0];
    const status = progressVal >= 100 ? 'Completed' : 'In Progress';
    const res = await updateTaskProgress(task.assignment_id, task.id, progressVal, status, comment || null, files);
    
    if (res.success) {
      toast({ title: "Progress updated successfully" });
      onTaskUpdated();
      setComment('');
      setFiles([]);
    } else {
      toast({ title: "Failed to update", description: res.error, variant: "destructive" });
    }
    setSaving(false);
  };

  const handleComplete = async () => {
    setSaving(true);
    const res = await completeTaskAssignment(task.assignment_id, task.id);
    if (res.success) {
      toast({ title: "Task marked as completed!" });
      onTaskUpdated();
      onClose();
    } else {
      toast({ title: "Failed to complete", description: res.error, variant: "destructive" });
    }
    setSaving(false);
  };

  const handleAccept = () => {
    setSignatureOpen(true);
  };

  const handleSignatureCapture = async (signatureDataUrl) => {
    setSaving(true);
    const res = await acceptTaskAssignment(task.assignment_id, signatureDataUrl);
    setSaving(false);
    if (res.success) {
      toast({ title: 'Task Accepted!', description: 'Your signature was recorded.' });
      onTaskUpdated();
    } else {
      toast({ title: 'Failed to accept task', description: res.error, variant: 'destructive' });
    }
  };

  const isCompleted = task.assignment_status === 'Completed';

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <div className="flex justify-between items-start pr-6">
            <DialogTitle className="text-xl">{task.title}</DialogTitle>
          </div>
          <div className="flex flex-wrap gap-2 mt-2">
            <Badge className={getPriorityColor(task.priority)}>{task.priority} Priority</Badge>
            <Badge className={getStatusColor(task.assignment_status)}>
              {getStatusIcon(task.assignment_status)} {task.assignment_status}
            </Badge>
            {task.deadline && (
              <Badge variant="outline" className="text-gray-600 bg-gray-50 border-gray-200">
                <Calendar className="w-3 h-3 mr-1" />
                Due: {format(new Date(task.deadline), 'MMM dd, yyyy')}
              </Badge>
            )}
          </div>
        </DialogHeader>

        <div className="space-y-6 py-4">
          <div>
            <h4 className="text-sm font-semibold text-gray-700 mb-1">Description</h4>
            <div className="bg-gray-50 p-3 rounded-md text-sm whitespace-pre-wrap text-gray-800">
              {task.description || 'No description provided.'}
            </div>
            {task.created_by_profile && (
              <p className="text-xs text-gray-500 mt-2">Assigned by: {task.created_by_profile.full_name}</p>
            )}
          </div>

          {task.assignment_status === 'Pending' && (
             <div className="bg-yellow-50 p-4 rounded-lg border border-yellow-200 flex flex-col items-center justify-center text-center space-y-3">
                 <AlertCircle className="w-8 h-8 text-yellow-500 mb-1" />
                 <p className="text-sm text-yellow-800 font-medium">You need to accept this task before you can update its progress.</p>
                 <Button 
                    onClick={handleAccept} 
                    disabled={saving}
                    className="bg-[#003D82] text-white w-full sm:w-auto"
                 >
                    {saving ? <Loader2 className="w-4 h-4 mr-2 animate-spin" /> : <Play className="w-4 h-4 mr-2" />}
                    Accept Task
                 </Button>
             </div>
          )}

          {!isCompleted && task.assignment_status !== 'Pending' && (
            <div className="bg-blue-50/50 p-4 rounded-lg border border-blue-100 space-y-4">
              <h4 className="font-semibold text-[#003D82] flex items-center">
                <AlertCircle className="w-4 h-4 mr-2" /> Update Progress
              </h4>
              
              <div className="space-y-3">
                <div className="flex justify-between">
                  <Label>Progress Slider</Label>
                  <span className="text-sm font-bold text-[#003D82]">{progress}%</span>
                </div>
                <Slider
                  defaultValue={[task.my_progress || 0]}
                  max={100}
                  step={5}
                  value={[typeof progress === 'number' ? progress : progress[0]]}
                  onValueChange={setProgress}
                  className="py-2"
                />
              </div>

              <div className="space-y-2">
                <Label>Update Comment (Optional)</Label>
                <Textarea 
                  placeholder="What did you work on?" 
                  value={comment}
                  onChange={(e) => setComment(e.target.value)}
                  className="bg-white text-gray-900"
                />
              </div>

              <div className="space-y-2">
                <Label className="flex items-center">
                  <Paperclip className="w-4 h-4 mr-2" /> Attachments
                </Label>
                <div className="flex items-center gap-2">
                  <Label htmlFor="task-file" className="cursor-pointer">
                    <div className="bg-white border text-sm px-3 py-1.5 rounded hover:bg-gray-50 flex items-center text-gray-700">
                      {uploading ? <Loader2 className="w-4 h-4 mr-2 animate-spin" /> : <Paperclip className="w-4 h-4 mr-2" />}
                      Add File
                    </div>
                  </Label>
                  <input id="task-file" type="file" multiple className="hidden" onChange={handleFileChange} disabled={uploading} />
                </div>
                {files.length > 0 && (
                  <div className="flex flex-wrap gap-2 mt-2">
                    {files.map((f, i) => (
                      <Badge key={i} variant="secondary" className="flex items-center gap-1 py-1">
                        <span className="truncate max-w-[150px]">{f.name}</span>
                        <X className="w-3 h-3 cursor-pointer hover:text-red-500" onClick={() => setFiles(files.filter((_, idx) => idx !== i))} />
                      </Badge>
                    ))}
                  </div>
                )}
              </div>

              <div className="flex justify-between pt-2">
                <Button 
                  onClick={handleUpdate} 
                  disabled={saving || uploading}
                  className="bg-[#003D82] text-white"
                >
                  {saving && <Loader2 className="w-4 h-4 mr-2 animate-spin" />}
                  Save Update
                </Button>

                {(progress === 100 || (Array.isArray(progress) && progress[0] === 100)) && (
                  <Button 
                    onClick={handleComplete} 
                    disabled={saving || uploading}
                    className="bg-green-600 hover:bg-green-700 text-white"
                  >
                    <CheckCircle className="w-4 h-4 mr-2" /> Mark as Completed
                  </Button>
                )}
              </div>
            </div>
          )}
        </div>
      </DialogContent>

      <SignaturePadModal
        isOpen={signatureOpen}
        onClose={() => setSignatureOpen(false)}
        onSignatureCapture={handleSignatureCapture}
        title="Sign to Accept Task"
        description="Draw your signature to confirm you accept this task assignment."
      />
    </Dialog>
  );
};

export default TaskDetailsModal;