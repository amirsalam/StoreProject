import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { Loader2, ShieldCheck } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface ChallengeForm {
    code: string;
    recovery_code: string;
    [key: string]: string;
}

export default function TwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<ChallengeForm>({
        code: '',
        recovery_code: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('two-factor.challenge'), {
            onFinish: () => reset('code', 'recovery_code'),
        });
    };

    return (
        <AuthLayout
            title="Two-factor authentication"
            description={
                useRecovery
                    ? 'Enter one of your recovery codes to sign in.'
                    : 'Open your authenticator app and enter the 6-digit code.'
            }
        >
            <Head title="Two-factor challenge" />

            <form onSubmit={submit} className="space-y-6">
                {useRecovery ? (
                    <div className="space-y-1.5">
                        <Label htmlFor="recovery_code">Recovery code</Label>
                        <Input
                            id="recovery_code"
                            autoFocus
                            autoComplete="one-time-code"
                            placeholder="abcde-12345"
                            value={data.recovery_code}
                            onChange={(e) => setData('recovery_code', e.target.value)}
                        />
                        <InputError message={errors.code} />
                    </div>
                ) : (
                    <div className="space-y-1.5">
                        <Label htmlFor="code">Authentication code</Label>
                        <Input
                            id="code"
                            autoFocus
                            inputMode="numeric"
                            pattern="\d{6}"
                            maxLength={6}
                            autoComplete="one-time-code"
                            placeholder="123456"
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value.replace(/\D/g, ''))}
                        />
                        <InputError message={errors.code} />
                    </div>
                )}

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing ? <Loader2 className="animate-spin" /> : <ShieldCheck />}
                    Verify and continue
                </Button>

                <p className="text-center text-xs text-muted-foreground">
                    <button
                        type="button"
                        onClick={() => setUseRecovery((r) => !r)}
                        className="underline-offset-4 hover:underline"
                    >
                        {useRecovery ? 'Use authenticator code instead' : "Lost your phone? Use a recovery code"}
                    </button>
                </p>
            </form>
        </AuthLayout>
    );
}
