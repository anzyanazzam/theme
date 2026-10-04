import styled from 'styled-components/macro';
import tw from 'twin.macro';

export default styled.div<{ $hoverable?: boolean }>`
    ${tw`flex rounded-lg no-underline text-neutral-300 items-center bg-neutral-200 p-4 border border-neutral-600 transition-all duration-150 overflow-hidden`};
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.12);

    ${(props) => props.$hoverable !== false && tw`hover:border-neutral-500`};

    & .icon {
        ${tw`rounded-full w-16 flex items-center justify-center bg-neutral-800 border border-neutral-600 text-white p-3`};
    }
`;