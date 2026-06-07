import React, { useState } from 'react';
import AgreementDocument from '@/components/admin/AgreementDocument';
import {
  generateAgreementPDFBlob,
  generateAndSaveAgreementPDF,
  downloadPDF,
  sendAgreementViaWhatsApp,
} from '@/services/agreementService';
import { usePageT } from '@/hooks/useSiteLabel';
import { useSiteLabel } from '@/hooks/useSiteLabel';
import './AgreementViewModal.css';

const AgreementViewModal = ({ shareholder, onClose }) => {
  const ta = usePageT('agreement');
  const tl = useSiteLabel();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  if (!shareholder) {
    return null;
  }

  const investmentDetails = {
    approvedShares: shareholder.shares_assigned || 0,
    totalInvestment: shareholder.investment_amount || 0,
    sharePrice: shareholder.shares_assigned > 0
      ? shareholder.investment_amount / shareholder.shares_assigned
      : 0,
  };

  const handleDownloadPDF = async () => {
    setLoading(true);
    setError(null);
    setSuccess(null);

    try {
      const { pdf, pdfBlob, filename } = await generateAgreementPDFBlob(
        shareholder.id,
        'agreement-document',
        shareholder.full_name
      );

      const downloadResult = downloadPDF(pdf, filename);
      if (!downloadResult.success) throw new Error(downloadResult.error);

      generateAndSaveAgreementPDF(shareholder.id, 'agreement-document', shareholder.full_name).catch(() => {});

      setSuccess(ta('download_success', 'PDF downloaded to your device.'));
    } catch (err) {
      console.error('AgreementViewModal: Download error:', err);
      setError(err.message || ta('download_error', 'Failed to generate PDF'));
    } finally {
      setLoading(false);
    }
  };

  const handlePrint = () => {
    try {
      setError(null);
      setSuccess(null);
      const element = document.getElementById('agreement-document');
      if (!element) throw new Error(ta('document_not_found', 'Agreement document not found'));

      const printWindow = window.open('', '_blank');
      if (!printWindow) throw new Error(ta('popup_blocked', 'Pop-up blocked. Allow pop-ups to print.'));

      printWindow.document.write(`
        <html><head><title>Agreement-${shareholder.full_name || 'Shareholder'}</title></head>
        <body>${element.outerHTML}</body></html>
      `);
      printWindow.document.close();
      printWindow.focus();
      printWindow.print();
    } catch (err) {
      setError(err.message || ta('print_error', 'Failed to print agreement'));
    }
  };

  const handleSendViaWhatsApp = async () => {
    setLoading(true);
    setError(null);
    setSuccess(null);

    try {
      const { pdfBlob, filename } = await generateAgreementPDFBlob(
        shareholder.id,
        'agreement-document',
        shareholder.full_name
      );

      const whatsappResult = await sendAgreementViaWhatsApp(
        shareholder.full_phone_number || shareholder.phone_number,
        shareholder.full_name,
        pdfBlob,
        filename,
        investmentDetails
      );

      if (!whatsappResult.success) {
        throw new Error(whatsappResult.error || ta('send_error', 'Failed to send agreement via WhatsApp'));
      }

      generateAndSaveAgreementPDF(shareholder.id, 'agreement-document', shareholder.full_name).catch(() => {});

      setSuccess(ta('send_success', 'Agreement text and PDF sent to investor via WhatsApp.'));
    } catch (err) {
      console.error('AgreementViewModal: WhatsApp send error:', err);
      setError(err.message || ta('send_error', 'Failed to send agreement via WhatsApp'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="agreement-modal-overlay" onClick={onClose}>
      <div className="agreement-modal-content" onClick={(e) => e.stopPropagation()}>
        <div className="agreement-modal-header">
          <h2>{ta('modal_title', 'Shareholder Agreement')} - {shareholder.full_name}</h2>
          <button onClick={onClose} className="btn-close" disabled={loading}>×</button>
        </div>

        <div className="agreement-modal-body">
          {error && <div className="error-message">⚠️ {error}</div>}
          {success && <div className="success-message">{success}</div>}
          <AgreementDocument shareholder={shareholder} isSignedView />
        </div>

        <div className="agreement-modal-footer">
          <button onClick={handleDownloadPDF} className="btn btn-primary" disabled={loading}>
            {loading ? `⏳ ${tl('common', 'Processing...')}` : `📥 ${ta('download_pdf', 'Download PDF')}`}
          </button>
          <button onClick={handlePrint} className="btn btn-secondary" disabled={loading}>
            🖨️ {ta('print', 'Print')}
          </button>
          <button onClick={handleSendViaWhatsApp} className="btn btn-success" disabled={loading}>
            {loading ? `⏳ ${tl('common', 'Sending...')}` : `💬 ${ta('send_investor', 'Send to Investor')}`}
          </button>
          <button onClick={onClose} className="btn btn-outline" disabled={loading}>{tl('common', 'Close')}</button>
        </div>
      </div>
    </div>
  );
};

export default AgreementViewModal;
