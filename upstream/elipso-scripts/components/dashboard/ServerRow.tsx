import React, { memo, useEffect, useRef, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEthernet, faHdd, faMemory, faMicrochip, faServer } from '@fortawesome/free-solid-svg-icons';
import { Link } from 'react-router-dom';
import { Server } from '@/api/server/getServer';
import getServerResourceUsage, { ServerPowerState, ServerStats } from '@/api/server/getServerResourceUsage';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import GreyRowBox from '@/components/elements/GreyRowBox';
import Spinner from '@/components/elements/Spinner';
import styled from 'styled-components/macro';
import isEqual from 'react-fast-compare';

const isAlarmState = (current: number, limit: number): boolean => limit > 0 && current / (limit * 1024 * 1024) >= 0.9;

const Icon = memo(
    styled(FontAwesomeIcon)<{ $alarm: boolean }>`
        ${(props) => (props.$alarm ? 'color: var(--elipso-error) !important;' : 'color: var(--elipso-muted) !important;')};
    `,
    isEqual
);

const IconDescription = memo(
    styled.p<{ $alarm: boolean }>`
        font-size: 14px;
        margin-left: 8px;
        ${(props) => (props.$alarm ? 'color: var(--elipso-error) !important;' : 'color: var(--elipso-body) !important;')};
    `,
    isEqual
);

const StatusIndicatorBox = styled(GreyRowBox)<{ $status: ServerPowerState | undefined }>`
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 16px;
    position: relative;

    & .status-bar {
        width: 4px;
        position: absolute;
        right: 0;
        z-index: 20;
        border-radius: 9999px;
        margin: 4px;
        opacity: 0.7;
        transition: all 0.15s ease;
        background: var(--elipso-error);

        ${({ $status }) =>
            !$status || $status === 'offline'
                ? 'background: var(--elipso-error)'
                : $status === 'running'
                ? 'background: var(--elipso-cyan)'
                : 'background: var(--elipso-warning)'};
    }

    &:hover .status-bar {
        opacity: 0.75;
    }
`;

type Timer = ReturnType<typeof setInterval>;

export default ({ server, className }: { server: Server; className?: string }) => {
    const interval = useRef<Timer>(null) as React.MutableRefObject<Timer>;
    const [isSuspended, setIsSuspended] = useState(server.status === 'suspended');
    const [stats, setStats] = useState<ServerStats | null>(null);

    const getStats = () =>
        getServerResourceUsage(server.uuid)
            .then((data) => setStats(data))
            .catch((error) => console.error(error));

    useEffect(() => {
        setIsSuspended(stats?.isSuspended || server.status === 'suspended');
    }, [stats?.isSuspended, server.status]);

    useEffect(() => {
        if (isSuspended || server.isNodeUnderMaintenance) return;

        getStats().then(() => {
            interval.current = setInterval(() => getStats(), 30000);
        });

        return () => {
            interval.current && clearInterval(interval.current);
        };
    }, [isSuspended, server.isNodeUnderMaintenance]);

    const alarms = { cpu: false, memory: false, disk: false };
    if (stats) {
        alarms.cpu = server.limits.cpu === 0 ? false : stats.cpuUsagePercent >= server.limits.cpu * 0.9;
        alarms.memory = isAlarmState(stats.memoryUsageInBytes, server.limits.memory);
        alarms.disk = server.limits.disk === 0 ? false : isAlarmState(stats.diskUsageInBytes, server.limits.disk);
    }

    const diskLimit = server.limits.disk !== 0 ? bytesToString(mbToBytes(server.limits.disk)) : 'Unlimited';
    const memoryLimit = server.limits.memory !== 0 ? bytesToString(mbToBytes(server.limits.memory)) : 'Unlimited';
    const cpuLimit = server.limits.cpu !== 0 ? server.limits.cpu + ' %' : 'Unlimited';

    return (
        <StatusIndicatorBox as={Link} to={`/server/${server.id}`} className={className} $status={stats?.status}>
            <div style={{ display: 'flex', alignItems: 'center' }} className={'col-span-12 sm:col-span-5 lg:col-span-6'}>
                <div className={'mr-4'}>
                    <FontAwesomeIcon icon={faServer} style={{ color: 'var(--elipso-muted)' }} />
                </div>
                <div>
                    <p style={{ color: 'var(--elipso-ink)', fontWeight: 600, fontSize: '18px', letterSpacing: '-0.03em', wordBreak: 'break-word' }}>{server.name}</p>
                    {!!server.description && (
                        <p style={{ color: 'var(--elipso-body)', fontSize: '14px', wordBreak: 'break-word', overflow: 'hidden', display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical' }}>{server.description}</p>
                    )}
                </div>
            </div>
            <div style={{ flex: 1, marginLeft: '16px' }} className={'lg:block lg:col-span-2 hidden'}>
                <div style={{ display: 'flex', justifyContent: 'center' }}>
                    <FontAwesomeIcon icon={faEthernet} style={{ color: 'var(--elipso-muted)' }} />
                    <p style={{ color: 'var(--elipso-body)', fontSize: '14px', marginLeft: '8px', fontFamily: 'var(--font-mono)' }}>
                        {server.allocations
                            .filter((alloc) => alloc.isDefault)
                            .map((allocation) => (
                                <React.Fragment key={allocation.ip + allocation.port.toString()}>
                                    {allocation.alias || ip(allocation.ip)}:{allocation.port}
                                </React.Fragment>
                            ))}
                    </p>
                </div>
            </div>
            <div className={'hidden col-span-7 lg:col-span-4 sm:flex items-baseline justify-center'}>
                {!stats || isSuspended || server.isNodeUnderMaintenance ? (
                    isSuspended ? (
                        <div style={{ flex: 1, textAlign: 'center' }}>
                            <span style={{ background: 'var(--elipso-error-soft)', borderRadius: '9999px', border: '1px solid var(--elipso-error)', padding: '4px 8px', color: 'var(--elipso-error)', fontSize: '12px', fontFamily: 'var(--font-mono)' }}>
                                {server.status === 'suspended' ? 'Suspended' : 'Connection Error'}
                            </span>
                        </div>
                    ) : server.isNodeUnderMaintenance ? (
                        <div style={{ flex: 1, textAlign: 'center' }}>
                            <span style={{ background: 'var(--elipso-warning-soft)', borderRadius: '9999px', border: '1px solid var(--elipso-warning)', padding: '4px 8px', color: 'var(--elipso-warning)', fontSize: '12px', fontFamily: 'var(--font-mono)' }}>
                                Under Maintenance
                            </span>
                        </div>
                    ) : server.isTransferring || server.status ? (
                        <div style={{ flex: 1, textAlign: 'center' }}>
                            <span style={{ background: 'var(--elipso-canvas-soft-2)', borderRadius: '9999px', border: '1px solid var(--elipso-hairline)', padding: '4px 8px', color: 'var(--elipso-body)', fontSize: '12px', fontFamily: 'var(--font-mono)' }}>
                                {server.isTransferring
                                    ? 'Transferring'
                                    : server.status === 'installing'
                                    ? 'Installing'
                                    : server.status === 'restoring_backup'
                                    ? 'Restoring Backup'
                                    : 'Unavailable'}
                            </span>
                        </div>
                    ) : (
                        <Spinner size={'small'} />
                    )
                ) : (
                    <React.Fragment>
                        <div style={{ flex: 1, marginLeft: '16px' }} className={'sm:block hidden'}>
                            <div style={{ display: 'flex', justifyContent: 'center' }}>
                                <Icon icon={faMicrochip} $alarm={alarms.cpu} />
                                <IconDescription $alarm={alarms.cpu}>
                                    {stats.cpuUsagePercent.toFixed(2)} %
                                </IconDescription>
                            </div>
                            <p style={{ color: 'var(--elipso-muted)', fontSize: '12px', textAlign: 'center', marginTop: '4px', fontFamily: 'var(--font-mono)' }}>of {cpuLimit}</p>
                        </div>
                        <div style={{ flex: 1, marginLeft: '16px' }} className={'sm:block hidden'}>
                            <div style={{ display: 'flex', justifyContent: 'center' }}>
                                <Icon icon={faMemory} $alarm={alarms.memory} />
                                <IconDescription $alarm={alarms.memory}>
                                    {bytesToString(stats.memoryUsageInBytes)}
                                </IconDescription>
                            </div>
                            <p style={{ color: 'var(--elipso-muted)', fontSize: '12px', textAlign: 'center', marginTop: '4px', fontFamily: 'var(--font-mono)' }}>of {memoryLimit}</p>
                        </div>
                        <div style={{ flex: 1, marginLeft: '16px' }} className={'sm:block hidden'}>
                            <div style={{ display: 'flex', justifyContent: 'center' }}>
                                <Icon icon={faHdd} $alarm={alarms.disk} />
                                <IconDescription $alarm={alarms.disk}>
                                    {bytesToString(stats.diskUsageInBytes)}
                                </IconDescription>
                            </div>
                            <p style={{ color: 'var(--elipso-muted)', fontSize: '12px', textAlign: 'center', marginTop: '4px', fontFamily: 'var(--font-mono)' }}>of {diskLimit}</p>
                        </div>
                    </React.Fragment>
                )}
            </div>
            <div className={'status-bar'} />
        </StatusIndicatorBox>
    );
};