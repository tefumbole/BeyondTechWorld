import React, { useState, useEffect } from 'react';
import { formatPrice } from '@/services/sharePriceService';
import { getPendingPaymentShareholders, updateShareholderPaymentStatus } from '@/services/shareholderService';
import { sendPaymentConfirmationViaWhatsApp } from '@/services/agreementService';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
  Loader2, User, Mail, Phone, Hash, DollarSign, Calendar, AlertCircle, CheckCircle, CreditCard,
} from 'lucide-react';
import { format } from 'date-fns';
import { useToast } from '@/components/ui/use-toast';

const PendingPaymentsPage = () => {
  const [loading, setLoading] = useState(true);
  const [shareholders, setShareholders] = useState([]);
  const [error, setError] = useState(null);
  const [busyId, setBusyId] = useState(null);
  const { toast } = useToast();

  const loadData = async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await getPendingPaymentShareholders();
      setShareholders(Array.isArray(data) ? data : []);
    } catch (err) {
      setError(err.message || 'Failed to load pending payments');
      setShareholders([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

  const markAsPaid = async (shareholder) => {
    setBusyId(shareholder.id);
    try {
      const result = await updateShareholderPaymentStatus(shareholder.id, 'paid');
      if (!result.success) throw new Error(result.error?.message || result.error || 'Update failed');

      try {
        await sendPaymentConfirmationViaWhatsApp(
          shareholder.full_phone_number || shareholder.phone_number,
          shareholder.full_name,
          {
            sharesCount: shareholder.shares_assigned,
            totalInvestment: shareholder.investment_amount,
          }
        );
      } catch (whatsappErr) {
        console.warn('[PENDING PAYMENTS] WhatsApp notification failed:', whatsappErr);
        toast({
          title: 'Payment marked as paid',
          description: `${shareholder.full_name} updated, but WhatsApp notification failed.`,
        });
        loadData();
        return;
      }

      toast({ title: 'Payment marked as paid', description: `${shareholder.full_name} updated and notified via WhatsApp.` });
      loadData();
    } catch (err) {
      toast({ title: 'Update failed', description: err.message, variant: 'destructive' });
    } finally {
      setBusyId(null);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <Loader2 className="w-12 h-12 animate-spin text-[#003D82]" />
      </div>
    );
  }

  if (error) {
    return (
      <Alert variant="destructive" className="max-w-2xl mx-auto mt-8">
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>{error}</AlertDescription>
      </Alert>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-800">Pending Payments</h1>
          <p className="text-gray-600 mt-1">Approved shareholders awaiting payment confirmation</p>
        </div>
        <Badge variant="outline" className="text-lg px-4 py-2 bg-yellow-50 border-yellow-300 text-yellow-800">
          <CreditCard className="w-4 h-4 mr-2" />
          {shareholders.length} Pending
        </Badge>
      </div>

      {shareholders.length === 0 ? (
        <Card className="max-w-2xl mx-auto">
          <CardContent className="p-12 text-center">
            <CheckCircle className="w-16 h-16 text-green-500 mx-auto mb-4" />
            <h3 className="text-xl font-bold text-gray-800 mb-2">No Pending Payments</h3>
            <p className="text-gray-600">All approved shareholders have completed payment.</p>
          </CardContent>
        </Card>
      ) : (
        <div className="grid gap-4">
          {shareholders.map((shareholder) => {
            const pricePerShare = shareholder.shares_assigned > 0
              ? shareholder.investment_amount / shareholder.shares_assigned
              : 0;

            return (
              <Card key={shareholder.id} className="border-2 border-yellow-200 shadow-md">
                <CardHeader className="bg-yellow-50">
                  <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                      <User className="w-5 h-5 text-yellow-700" />
                      <CardTitle className="text-xl">{shareholder.full_name || 'N/A'}</CardTitle>
                      <Badge className="bg-yellow-600 text-white capitalize">
                        {shareholder.payment_status || 'pending'}
                      </Badge>
                    </div>
                    <Button
                      className="bg-green-600 hover:bg-green-700"
                      disabled={busyId === shareholder.id}
                      onClick={() => markAsPaid(shareholder)}
                    >
                      {busyId === shareholder.id ? (
                        <Loader2 className="w-4 h-4 animate-spin mr-2" />
                      ) : (
                        <CheckCircle className="w-4 h-4 mr-2" />
                      )}
                      Mark as Paid
                    </Button>
                  </div>
                </CardHeader>
                <CardContent className="p-6 space-y-4">
                  <div className="grid md:grid-cols-2 gap-3 text-sm text-gray-600">
                    <div className="flex items-center gap-2"><Mail className="w-4 h-4" />{shareholder.email || 'N/A'}</div>
                    <div className="flex items-center gap-2"><Phone className="w-4 h-4" />{shareholder.full_phone_number || shareholder.phone_number || 'N/A'}</div>
                    <div className="flex items-center gap-2">
                      <Calendar className="w-4 h-4" />
                      Approved {shareholder.approved_at ? format(new Date(shareholder.approved_at), 'MMM dd, yyyy') : 'N/A'}
                    </div>
                    <div className="flex items-center gap-2"><Hash className="w-4 h-4" />{shareholder.shares_assigned || 0} shares</div>
                  </div>
                  <div className="grid md:grid-cols-2 gap-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <div>
                      <p className="text-xs text-gray-600">Price Per Share</p>
                      <p className="text-lg font-bold text-purple-800">{formatPrice(pricePerShare)}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-600">Total Investment Due</p>
                      <p className="text-lg font-bold text-green-800">{formatPrice(shareholder.investment_amount || 0)}</p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
};

export default PendingPaymentsPage;
