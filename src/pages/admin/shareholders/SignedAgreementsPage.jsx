import React, { useState, useEffect } from 'react';
import { formatPrice } from '@/services/sharePriceService';
import { formatCountryCodeDisplay } from '@/services/countryCodeService';
import { getSignedShareholderAgreements } from '@/services/shareholderService';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import AgreementViewModal from '@/components/admin/AgreementViewModal';
import {
  Loader2,
  CheckCircle,
  User,
  Mail,
  Phone,
  MapPin,
  Hash,
  DollarSign,
  Calendar,
  FileSignature,
  Globe,
  AlertCircle,
  FileText
} from 'lucide-react';
import { format } from 'date-fns';
import { usePageT, useSiteLabel } from '@/hooks/useSiteLabel';

const SignedAgreementsPage = () => {
  const ts = usePageT('shareholders');
  const tl = useSiteLabel();
  const [loading, setLoading] = useState(true);
  const [approvedShareholders, setApprovedShareholders] = useState([]);
  const [error, setError] = useState(null);
  const [selectedShareholder, setSelectedShareholder] = useState(null);

  const fetchApprovedShareholders = async () => {
    console.log('[SIGNED] Fetching signed shareholder agreements');
    setLoading(true);
    setError(null);

    try {
      const data = await getSignedShareholderAgreements();
      console.log('[SIGNED] Fetched', data.length, 'signed agreements');
      setApprovedShareholders(Array.isArray(data) ? data : []);
    } catch (err) {
      console.error('[SIGNED] Error fetching approved shareholders:', err);
      setError(err.message || 'Failed to load approved shareholders');
      setApprovedShareholders([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchApprovedShareholders();
  }, []);

  const handleViewAgreement = (shareholder) => {
    console.log('[SIGNED] Opening agreement for shareholder:', shareholder.id);
    setSelectedShareholder(shareholder);
  };

  const handleCloseModal = () => {
    console.log('[SIGNED] Closing agreement modal');
    setSelectedShareholder(null);
    // Refresh data after modal closes (in case PDF was generated)
    fetchApprovedShareholders();
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-center">
          <Loader2 className="w-12 h-12 animate-spin text-[#003D82] mx-auto mb-4" />
          <p className="text-gray-600">{ts('loading_approved', 'Loading approved shareholders...')}</p>
        </div>
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

  if (approvedShareholders.length === 0) {
    return (
      <Card className="max-w-2xl mx-auto mt-8">
        <CardContent className="p-12 text-center">
          <FileSignature className="w-16 h-16 text-gray-400 mx-auto mb-4" />
          <h3 className="text-xl font-bold text-gray-800 mb-2">{ts('no_signed_agreements', 'No Signed Agreements')}</h3>
          <p className="text-gray-600">{ts('no_approved_yet', 'No approved shareholders with signed agreements yet.')}</p>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-800">{ts('signed_agreements_title', 'Signed Shareholder Agreements')}</h1>
          <p className="text-gray-600 mt-1">{ts('signed_agreements_subtitle', 'View all approved shareholders with signed agreements')}</p>
        </div>
        <Badge variant="outline" className="text-lg px-4 py-2 bg-green-50 border-green-300 text-green-800">
          <CheckCircle className="w-4 h-4 mr-2" />
          {approvedShareholders.length} {ts('signed_count', 'Signed')}
        </Badge>
      </div>

      <div className="grid gap-4">
        {approvedShareholders.map(shareholder => {
          const pricePerShare = shareholder.shares_assigned > 0
            ? shareholder.investment_amount / shareholder.shares_assigned
            : 0;

          return (
            <Card key={shareholder.id} className={`border-2 shadow-lg hover:shadow-xl transition-shadow ${shareholder.status === 'approved' ? 'border-green-200' : 'border-amber-200'}`}>
              <CardHeader className={shareholder.status === 'approved' ? 'bg-green-50' : 'bg-amber-50'}>
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center gap-3 mb-2">
                      <User className="w-5 h-5 text-green-600" />
                      <CardTitle className="text-xl">{shareholder.full_name || 'N/A'}</CardTitle>
                      {shareholder.status === 'approved' ? (
                        <Badge className="bg-green-600 text-white">
                          <CheckCircle className="w-3 h-3 mr-1" />
                          {tl('common', 'Approved')}
                        </Badge>
                      ) : (
                        <Badge className="bg-amber-500 text-white">
                          {ts('pending_approval', 'Pending Approval')}
                        </Badge>
                      )}
                    </div>
                    <div className="grid md:grid-cols-2 gap-2 text-sm text-gray-600">
                      <div className="flex items-center gap-2">
                        <Mail className="w-4 h-4 text-gray-400" />
                        <span>{shareholder.email || 'N/A'}</span>
                      </div>
                      <div className="flex items-center gap-2">
                        <Phone className="w-4 h-4 text-gray-400" />
                        <span>{shareholder.full_phone_number || 'N/A'}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </CardHeader>

              <CardContent className="p-6">
                <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                  <div className="flex items-center gap-2">
                    <Globe className="w-4 h-4 text-gray-400" />
                    <div>
                      <p className="text-xs text-gray-500">{ts('country_code', 'Country Code')}</p>
                      <p className="font-medium">{formatCountryCodeDisplay(shareholder.country_code)}</p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <MapPin className="w-4 h-4 text-gray-400" />
                    <div>
                      <p className="text-xs text-gray-500">{tl('pages', 'Address')}</p>
                      <p className="font-medium">{shareholder.address || 'N/A'}</p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <Calendar className="w-4 h-4 text-gray-400" />
                    <div>
                      <p className="text-xs text-gray-500">{ts('approved_date', 'Approved Date')}</p>
                      <p className="font-medium">
                        {shareholder.approved_at
                          ? format(new Date(shareholder.approved_at), 'MMM dd, yyyy')
                          : 'N/A'}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <DollarSign className="w-4 h-4 text-gray-400" />
                    <div>
                      <p className="text-xs text-gray-500">{ts('payment_status', 'Payment Status')}</p>
                      <Badge
                        variant="outline"
                        className={
                          shareholder.payment_status === 'completed' ||
                          shareholder.payment_status === 'paid'
                            ? 'bg-green-50 text-green-700 border-green-300'
                            : 'bg-yellow-50 text-yellow-700 border-yellow-300'
                        }
                      >
                        {shareholder.payment_status || 'pending'}
                      </Badge>
                    </div>
                  </div>
                </div>

                <div className="grid md:grid-cols-3 gap-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                  <div className="flex items-center gap-2">
                    <Hash className="w-5 h-5 text-blue-600" />
                    <div>
                      <p className="text-xs text-gray-600">{ts('approved_shares', 'Approved Shares')}</p>
                      <p className="text-lg font-bold text-blue-800">{shareholder.shares_assigned || 0}</p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <DollarSign className="w-5 h-5 text-purple-600" />
                    <div>
                      <p className="text-xs text-gray-600">{ts('price_per_share', 'Price Per Share')}</p>
                      <p className="text-lg font-bold text-purple-800">
                        {formatPrice(pricePerShare)}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <DollarSign className="w-5 h-5 text-green-600" />
                    <div>
                      <p className="text-xs text-gray-600">{ts('total_investment', 'Total Investment')}</p>
                      <p className="text-lg font-bold text-green-800">
                        {formatPrice(shareholder.investment_amount || 0)}
                      </p>
                    </div>
                  </div>
                </div>

                {shareholder.signature && (
                  <div className="mt-4 p-4 bg-gray-50 rounded-lg border">
                    <div className="flex items-center gap-2 mb-3">
                      <FileSignature className="w-4 h-4 text-gray-600" />
                      <span className="font-medium text-gray-700">{ts('digital_signature', 'Digital Signature')}</span>
                    </div>
                    <div className="bg-white border border-gray-300 rounded-lg p-2 inline-block">
                      <img
                        src={shareholder.signature}
                        alt="Shareholder signature"
                        className="max-w-[300px] h-auto"
                      />
                    </div>
                  </div>
                )}

                <div className="mt-4">
                  <Button
                    onClick={() => handleViewAgreement(shareholder)}
                    className="w-full bg-[#003D82] hover:bg-[#002855] text-white"
                  >
                    <FileText className="w-4 h-4 mr-2" />
                    {ts('view_agreement_document', 'View Agreement Document')}
                  </Button>
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      {/* Agreement View Modal */}
      {selectedShareholder && (
        <AgreementViewModal
          shareholder={selectedShareholder}
          onClose={handleCloseModal}
        />
      )}
    </div>
  );
};

export default SignedAgreementsPage;