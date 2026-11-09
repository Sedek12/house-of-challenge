/** Rendu Markdown minimal (titres + paragraphes), sans HTML injecté. */
export function Markdown({ text }: { text: string }) {
    return (
        <div className="grid gap-2 text-sm leading-relaxed">
            {text.split(/\n{2,}|\n(?=#)/).map((block, i) => {
                const m = block.match(/^(#{1,3})\s+(.*)$/s);
                if (m) {
                    const [head, ...rest] = m[2].split('\n');
                    const Tag = m[1].length === 1 ? 'h2' : 'h3';
                    return (
                        <div key={i}>
                            <Tag className={m[1].length === 1 ? 'mt-3 text-xl font-bold' : 'mt-3 text-lg font-semibold'}>{head}</Tag>
                            {rest.length > 0 && <p className="mt-1 whitespace-pre-line">{rest.join('\n')}</p>}
                        </div>
                    );
                }
                return <p key={i} className="whitespace-pre-line">{block}</p>;
            })}
        </div>
    );
}
