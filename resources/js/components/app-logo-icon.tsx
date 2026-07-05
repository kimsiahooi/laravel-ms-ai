import { Factory, type LucideProps } from 'lucide-react';

// Manufacturing brand mark — lucide's Factory icon. It's stroke-based, so
// usages should color it with text-* (currentColor), not fill-current.
export default function AppLogoIcon(props: LucideProps) {
    return <Factory {...props} />;
}
