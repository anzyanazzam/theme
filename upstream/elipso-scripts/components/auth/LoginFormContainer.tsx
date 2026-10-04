import React, { forwardRef } from 'react';
import { Form } from 'formik';
import styled from 'styled-components/macro';
import { breakpoint } from '@/theme';
import FlashMessageRender from '@/components/FlashMessageRender';
import tw from 'twin.macro';

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
};

const Container = styled.div`
    width: 100%;
    max-width: 34rem;
    margin: 0 auto;

    ${breakpoint('sm')`
        width: min(100%, 34rem);
    `};

    ${breakpoint('md')`
        ${tw`px-2`}
    `};
`;

const Shell = styled.div`
    ${tw`w-full mx-auto p-8 md:p-10 overflow-hidden`};
    border-radius: 0.75rem;
    border: 1px solid var(--elipso-hairline, #1e293b);
    background: var(--elipso-canvas-raised, #0f1420);
    box-shadow: var(--elipso-shadow-panel, 0 4px 12px rgba(0, 0, 0, 0.25));
`;

const Eyebrow = styled.p`
    ${tw`text-xs uppercase mb-3`};
    color: var(--elipso-muted, #64748b);
    letter-spacing: 0.08em;
`;

const Mark = styled.div`
    ${tw`mb-8 flex items-center justify-between`};
`;

const Brand = styled.div`
    ${tw`text-sm font-medium`};
    color: var(--elipso-body, #94a3b8);
    letter-spacing: 0.02em;
`;

const Dot = styled.div`
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 9999px;
    background: linear-gradient(135deg, var(--elipso-link, #3b82f6) 0%, var(--elipso-cyan, #50e3c2) 100%);
    box-shadow: 0 0 0 0.25rem rgb(59 130 246 / 0.08);
`;

export default forwardRef<HTMLFormElement, Props>(({ title, ...props }, ref) => (
    <Container>
        {title && (
            <h2
                css={tw`text-3xl md:text-4xl text-left text-neutral-100 font-semibold pb-6`}
                style={{ letterSpacing: '-0.035em', lineHeight: 1.1 }}
            >
                {title}
            </h2>
        )}
        <FlashMessageRender css={tw`mb-2 px-1`} />
        <Form {...props} ref={ref}>
            <Shell>
                <Mark>
                    <Brand>Pterodactyl Panel</Brand>
                    <Dot />
                </Mark>
                <Eyebrow>Secure Access</Eyebrow>
                <div>{props.children}</div>
            </Shell>
        </Form>
        <p css={tw`text-left text-neutral-500 text-xs mt-5`}>
            &copy; 2015 - {new Date().getFullYear()}&nbsp;
            <a
                rel={'noopener nofollow noreferrer'}
                href={'https://pterodactyl.io'}
                target={'_blank'}
                css={tw`no-underline text-neutral-500 hover:text-neutral-300`}
            >
                Pterodactyl Software
            </a>
        </p>
    </Container>
));
