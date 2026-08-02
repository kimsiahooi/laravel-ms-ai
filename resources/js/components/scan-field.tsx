import { Camera, ScanBarcode } from 'lucide-react';
import { useRef, useState } from 'react';
import { BarcodeScanner } from '@/components/barcode-scanner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type ScanFieldProps = {
    /** Fired with a trimmed code from Enter (hardware scanner) or the camera. */
    onScan: (code: string) => void;
    id?: string;
    placeholder?: string;
    /** Accessible name for the input + camera button context. */
    label?: string;
    disabled?: boolean;
};

/**
 * The shared scan affordance: a text input a USB/Bluetooth scanner types the code
 * into (Enter submits), plus a camera button for phones/tablets. Both funnel to
 * `onScan`; the input clears and refocuses after each scan for rapid scanning.
 */
export function ScanField({
    onScan,
    id,
    placeholder = 'Scan or type a barcode…',
    label = 'Scan item',
    disabled,
}: ScanFieldProps) {
    const [value, setValue] = useState('');
    const [cameraOpen, setCameraOpen] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    const submit = (code: string) => {
        const trimmed = code.trim();
        if (!trimmed) {
            return;
        }
        onScan(trimmed);
        setValue('');
        inputRef.current?.focus();
    };

    return (
        <div className="flex items-center gap-2">
            <div className="relative flex-1">
                <ScanBarcode
                    className="-translate-y-1/2 pointer-events-none absolute top-1/2 left-3 size-4 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    ref={inputRef}
                    id={id}
                    type="text"
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            submit(value);
                        }
                    }}
                    placeholder={placeholder}
                    aria-label={label}
                    disabled={disabled}
                    autoComplete="off"
                    className="pl-9"
                />
            </div>
            <Button
                type="button"
                variant="outline"
                size="icon"
                onClick={() => setCameraOpen(true)}
                disabled={disabled}
                aria-label="Scan with camera"
            >
                <Camera className="size-4" />
            </Button>

            <BarcodeScanner
                open={cameraOpen}
                onOpenChange={setCameraOpen}
                onDetected={submit}
            />
        </div>
    );
}
