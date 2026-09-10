import React, { useEffect, useMemo, useState } from 'react';

export interface PagarModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    amount: number; // Em Meticais (MZN)
    reference: string;
    title?: string;
    initialPhone?: string;
    minAmount?: number;
    maxAmount?: number;
    endpoints: {
        paymentUrl: string; // Rota POST da sua aplicação Laravel para criar o pagamento
        statusUrl?: string; // Rota GET para consultar o estado do pagamento (polling)
    };
    onSuccess?: (data?: any) => void;
    onError?: (error?: any) => void;
}

export default function PagarModal({
    open,
    onOpenChange,
    amount,
    reference,
    title = 'Pagamento Seguro',
    initialPhone = '',
    minAmount = 20,
    maxAmount = 40000,
    endpoints,
    onSuccess,
    onError,
}: PagarModalProps) {
    const [phone, setPhone] = useState(initialPhone);
    const [loading, setLoading] = useState(false);
    const [stkSent, setStkSent] = useState(false);
    const [isPaid, setIsPaid] = useState(false);
    const [errorMsg, setErrorMsg] = useState<string | null>(null);
    const [paymentId, setPaymentId] = useState<string | null>(null);

    const isAmountValid = amount >= minAmount && amount <= maxAmount;

    useEffect(() => {
        if (open) {
            setPhone(initialPhone);
            setLoading(false);
            setStkSent(false);
            setIsPaid(false);
            setErrorMsg(null);
            setPaymentId(null);
        }
    }, [open, initialPhone]);

    // Polling contínuo de status se statusUrl for fornecido
    useEffect(() => {
        if (!stkSent || isPaid || !open || !endpoints.statusUrl) return;

        const interval = setInterval(async () => {
            try {
                const url = paymentId
                    ? `${endpoints.statusUrl}?payment_id=${encodeURIComponent(paymentId)}&reference=${encodeURIComponent(reference)}`
                    : `${endpoints.statusUrl}?reference=${encodeURIComponent(reference)}`;

                const res = await fetch(url, {
                    headers: { Accept: 'application/json' },
                });

                if (res.ok) {
                    const data = await res.json();
                    if (data.is_paid || data.paid || data.status === 'COMPLETED' || data.status === 'SUCCESS') {
                        setIsPaid(true);
                        setStkSent(false);
                        clearInterval(interval);
                        if (onSuccess) setTimeout(() => onSuccess(data), 1200);
                    } else if (data.status === 'FAILED' || data.status === 'EXPIRED') {
                        setErrorMsg(data.message || 'O pagamento falhou ou expirou. Tente novamente.');
                        setStkSent(false);
                        clearInterval(interval);
                        onError?.(data);
                    }
                }
            } catch {
                // Silêncio em falhas transitórias de conexão de rede
            }
        }, 3000);

        return () => clearInterval(interval);
    }, [stkSent, isPaid, open, endpoints.statusUrl, paymentId, reference, onSuccess, onError]);

    // Deteção inteligente da operadora moçambicana
    const operator = useMemo(() => {
        const clean = phone.replace(/\D/g, '');
        const local = clean.startsWith('258') ? clean.slice(3) : clean;

        if (local.startsWith('84') || local.startsWith('85')) {
            return {
                name: 'Vodacom M-Pesa',
                color: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-950/40 dark:text-red-400 dark:border-red-900',
                method: 'MPESA',
            };
        }
        if (local.startsWith('86') || local.startsWith('87')) {
            return {
                name: 'Movitel e-Mola',
                color: 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-950/40 dark:text-orange-400 dark:border-orange-900',
                method: 'EMOLA',
            };
        }
        return null;
    }, [phone]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!isAmountValid) {
            setErrorMsg(`O valor deve estar entre ${minAmount.toLocaleString()} MZN e ${maxAmount.toLocaleString()} MZN.`);
            return;
        }

        if (!operator) {
            setErrorMsg('Por favor introduza um número válido M-Pesa (84/85) ou e-Mola (86/87).');
            return;
        }

        setLoading(true);
        setErrorMsg(null);

        try {
            const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
            const res = await fetch(endpoints.paymentUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    phone,
                    amount,
                    reference,
                    method: operator.method,
                }),
            });

            const data = await res.json();

            if (data.success || res.ok) {
                if (data.paymentId || data.payment_id) {
                    setPaymentId(data.paymentId || data.payment_id);
                }

                if (data.status === 'COMPLETED' || data.status === 'SUCCESS' || data.is_paid) {
                    setIsPaid(true);
                    onSuccess?.(data);
                } else {
                    setStkSent(true);
                }
            } else {
                setErrorMsg(data.message || 'Não foi possível inicializar o pagamento.');
                onError?.(data);
            }
        } catch (err: any) {
            setErrorMsg('Erro de comunicação com o servidor. Verifique a sua conexão.');
            onError?.(err);
        } finally {
            setLoading(false);
        }
    };

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
            <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                {/* Cabeçalho */}
                <div className="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <h3 className="text-lg font-bold text-slate-900 dark:text-white">{title}</h3>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Ref: {reference}</p>
                    </div>
                    <button
                        type="button"
                        onClick={() => onOpenChange(false)}
                        className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-lg text-lg leading-none"
                    >
                        ✕
                    </button>
                </div>

                <div className="p-6">
                    {/* Resumo do Valor */}
                    <div className="mb-5 rounded-xl bg-amber-50 dark:bg-amber-950/30 p-4 text-center border border-amber-200 dark:border-amber-900/60">
                        <span className="text-[11px] uppercase tracking-wider font-bold text-amber-800 dark:text-amber-400">
                            Total a Cobrar
                        </span>
                        <div className="text-3xl font-extrabold text-amber-950 dark:text-amber-200 mt-1">
                            {amount.toLocaleString('pt-MZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} MZN
                        </div>
                        <span className="text-[11px] text-amber-700 dark:text-amber-400/80 block mt-1">
                            Limites: {minAmount.toLocaleString()} a {maxAmount.toLocaleString()} MZN
                        </span>
                    </div>

                    {isPaid ? (
                        <div className="py-6 text-center space-y-3">
                            <div className="w-16 h-16 bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 rounded-full flex items-center justify-center mx-auto shadow-sm">
                                <svg className="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <h4 className="text-xl font-bold text-emerald-600 dark:text-emerald-400">Pagamento Confirmado!</h4>
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                A sua transação foi concluída com sucesso via Pagar.co.mz.
                            </p>
                        </div>
                    ) : stkSent ? (
                        <div className="py-6 text-center space-y-4">
                            <div className="relative mx-auto w-16 h-16 bg-amber-100 dark:bg-amber-950 text-amber-600 dark:text-amber-400 rounded-full flex items-center justify-center">
                                <svg className="w-8 h-8 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                <span className="absolute inset-0 rounded-full border-2 border-amber-500 animate-ping opacity-75" />
                            </div>
                            <div>
                                <h4 className="font-semibold text-lg text-slate-900 dark:text-white">Confirme no seu telemóvel</h4>
                                <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                    Enviámos o pedido para <strong>{phone}</strong>. Digite o seu <strong>PIN</strong> na notificação para autorizar o débito.
                                </p>
                            </div>
                            <div className="flex items-center justify-center gap-2 text-xs text-amber-600 dark:text-amber-400 font-medium">
                                <span className="w-2 h-2 rounded-full bg-amber-500 animate-ping" /> Aguardando autorização do cliente...
                            </div>
                        </div>
                    ) : (
                        <form onSubmit={handleSubmit} className="space-y-4">
                            {errorMsg && (
                                <div className="p-3 text-xs bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-300 rounded-xl border border-red-200 dark:border-red-900">
                                    {errorMsg}
                                </div>
                            )}

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Número de Telemóvel (M-Pesa ou e-Mola)
                                </label>
                                <input
                                    type="tel"
                                    placeholder="Ex: 84XXXXXXX ou 86XXXXXXX"
                                    value={phone}
                                    onChange={(e) => setPhone(e.target.value)}
                                    className="w-full px-3.5 py-2.5 text-sm border rounded-xl dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500 text-slate-900 dark:text-white"
                                    required
                                    autoFocus
                                />
                            </div>

                            {operator && (
                                <div className="flex items-center gap-2">
                                    <span className={`text-[11px] font-semibold px-2.5 py-0.5 rounded-full border ${operator.color}`}>
                                        ● {operator.name}
                                    </span>
                                </div>
                            )}

                            <button
                                type="submit"
                                disabled={loading || !isAmountValid || phone.replace(/\D/g, '').length < 9}
                                className="w-full py-3 px-4 text-sm font-bold text-black bg-amber-400 hover:bg-amber-500 active:bg-amber-600 disabled:opacity-50 rounded-xl shadow-md transition"
                            >
                                {loading ? 'A enviar pedido...' : 'Pagar via Pagar.co.mz'}
                            </button>
                        </form>
                    )}
                </div>

                {/* Rodapé */}
                <div className="px-6 py-3 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800 flex justify-between items-center text-[11px] text-slate-400">
                    <span>Powered by Pagar.co.mz</span>
                    <button
                        type="button"
                        onClick={() => onOpenChange(false)}
                        className="font-semibold text-slate-600 dark:text-slate-300 hover:underline"
                    >
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    );
}
