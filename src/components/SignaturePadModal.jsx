import React, { useRef, useState, useEffect } from 'react';
import SignatureCanvas from 'react-signature-canvas';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Eraser, Check, X } from 'lucide-react';

const SignaturePadModal = ({ isOpen, onClose, onSignatureCapture, title = 'Sign Your Agreement', description = 'Draw your signature above using your mouse, trackpad, or touchscreen' }) => {
  const sigPadRef = useRef(null);
  const [isEmpty, setIsEmpty] = useState(true);

  useEffect(() => {
    if (isOpen && sigPadRef.current) {
      sigPadRef.current.clear();
      setIsEmpty(true);
    }
  }, [isOpen]);

  const handleClear = () => {
    if (sigPadRef.current) {
      sigPadRef.current.clear();
      setIsEmpty(true);
    }
  };

  const handleConfirm = () => {
    if (sigPadRef.current && !isEmpty) {
      const signatureDataUrl = sigPadRef.current.toDataURL('image/png');
      onSignatureCapture(signatureDataUrl);
      onClose();
    }
  };

  const handleCancel = () => {
    if (sigPadRef.current) {
      sigPadRef.current.clear();
    }
    setIsEmpty(true);
    onClose();
  };

  const handleBeginStroke = () => {
    setIsEmpty(false);
  };

  return (
    <Dialog open={isOpen} onOpenChange={handleCancel}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle className="text-2xl font-bold text-gray-800">{title}</DialogTitle>
          <DialogDescription className="text-gray-600">
            {description}
          </DialogDescription>
        </DialogHeader>

        <div className="signature-canvas-wrapper border-2 border-gray-300 rounded-lg bg-white overflow-hidden">
          <SignatureCanvas
            ref={sigPadRef}
            canvasProps={{
              width: 600,
              height: 300,
              className: 'signature-canvas'
            }}
            backgroundColor="rgba(255, 255, 255, 1)"
            penColor="black"
            minWidth={1}
            maxWidth={3}
            onBegin={handleBeginStroke}
          />
        </div>

        <DialogFooter className="flex gap-3">
          <Button
            type="button"
            variant="outline"
            onClick={handleCancel}
            className="flex items-center gap-2"
          >
            <X className="w-4 h-4" />
            Cancel
          </Button>
          
          <Button
            type="button"
            variant="outline"
            onClick={handleClear}
            disabled={isEmpty}
            className="flex items-center gap-2"
          >
            <Eraser className="w-4 h-4" />
            Clear
          </Button>

          <Button
            type="button"
            onClick={handleConfirm}
            disabled={isEmpty}
            className="flex items-center gap-2 bg-[#003D82] hover:bg-[#002855]"
          >
            <Check className="w-4 h-4" />
            Confirm Signature
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};

export default SignaturePadModal;